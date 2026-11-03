<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require dirname(__DIR__).'/includes/bootstrap.php';
require dirname(__DIR__).'/includes/ai.php';
require dirname(__DIR__).'/includes/mail.php';
function check(bool $condition, string $description): void { if (!$condition) { fwrite(STDERR,"FAIL: $description\n"); exit(1); } }
$clear=['summary'=>'Fuel receipt','requires_review'=>false,'image_quality'=>'good','uncertainty'=>null,'is_receipt'=>true,'currency'=>'AED','amount'=>200.0,'category'=>'Fuel'];
$decision=auto_approval_decision('expense','completed',json_encode($clear),1);
check($decision===['amount'=>'200.00','category'=>'Fuel'],'clear AED receipt is approved');
foreach ([
    ['requires_review'=>true], ['requires_review'=>null], ['image_quality'=>'unclear'],
    ['uncertainty'=>'Total is hard to read'], ['is_receipt'=>false], ['currency'=>'USD'],
    ['currency'=>null], ['amount'=>null], ['amount'=>0], ['amount'=>200.123], ['summary'=>''],
] as $change) {
    $input=array_replace($clear,$change);
    check(auto_approval_decision('expense','completed',json_encode($input),1)===null,'uncertain expense remains pending: '.json_encode($change));
}
$withoutUncertainty=$clear; unset($withoutUncertainty['uncertainty']);
check(auto_approval_decision('expense','completed',json_encode($withoutUncertainty),1)===null,'missing uncertainty remains pending');
check(auto_approval_decision('expense','failed',json_encode($clear),1)===null,'failed AI remains pending');
check(auto_approval_decision('expense','completed',json_encode($clear),2)===null,'multiple receipt photos remain pending');
check(auto_approval_decision('delivery','completed',json_encode($clear),1)===['amount'=>null,'category'=>''],'clear non-financial report approved without amount');
check(auto_approval_decision('bad-type','completed',json_encode($clear),1)===null,'invalid type never approved');
[$html,$text]=report_email_content(15,['name'=>'<Worker>'],'expense',json_encode($clear),['status'=>'approved','amount'=>'200.00','category'=>'Fuel','client'=>'<Client>']);
check(str_contains($html,'AED 200.00') && str_contains($html,'Calculation is wrong') && str_contains($html,'action=review'),'approved email shows counted amount and review action');
check(str_contains($html,'&lt;Worker&gt;') && str_contains($html,'&lt;Client&gt;') && !str_contains($html,'<Client>'),'email escapes user content');
check(str_contains($text,'Calculation is wrong') && str_contains($text,'Original photos are attached'),'plain text fallback contains action');
[$html,$text]=report_email_content(16,['name'=>'Sajjad'],'expense',json_encode($clear),['status'=>'pending','amount'=>null,'category'=>'','client'=>null]);
check(str_contains($html,'NOT COUNTED') && str_contains($html,'Review and approve') && str_contains($html,'action=approve') && str_contains($html,'Not assigned'),'pending email shows uncounted suggestion and approve action');
[$html,$text]=report_email_content(17,['name'=>'Sajjad'],'expense',json_encode($clear),['status'=>'approved','amount'=>'200.00','category'=>'Fuel','client'=>'Client','last_decision'=>'AI auto-approved']);
check(str_contains($html,'AI approved · counted') && str_contains($html,'COUNTED TOTAL') && str_contains($html,'ACTIVITY') && str_contains($html,'STATUS'),'automatic approval and three summary cards are clear');
[$html,$text]=report_email_content(18,['name'=>'Sajjad'],'expense',json_encode($clear),['status'=>'pending','amount'=>null,'ai_status'=>'completed','photo_count'=>2]);
check(str_contains($html,'Multiple expense photos need a manual total check.'),'multi-photo review reason is shown');
[$html,$text]=report_email_content(19,['name'=>'Sajjad'],'expense',null,['status'=>'pending','amount'=>null,'ai_status'=>'failed','photo_count'=>1]);
check(str_contains($html,'AI analysis was unavailable') && str_contains($html,'TOTAL · NOT COUNTED'),'failed analysis is not counted');
$report=['type'=>'expense','amount'=>null,'category'=>'','notes'=>'','last_decision'=>null];
$ai=['amount'=>200,'currency'=>'AED','category'=>'Fuel','summary'=>'Fuel purchase'];
$fields=review_field_values($report,$ai);
check($fields['amount']==='200.00' && $fields['category']==='Fuel' && $fields['notes']==='Fuel purchase' && $fields['prefilled'],'unreviewed report prefills AI fields');
$existing=array_replace($report,['amount'=>'175.00','category'=>'Travel','notes'=>'Already edited']);
$fields=review_field_values($existing,$ai);
check($fields['amount']==='175.00' && $fields['category']==='Travel' && $fields['notes']==='Already edited','existing saved management values win');
$fields=review_field_values(array_replace($report,['last_decision'=>'review updated']),$ai);
check($fields['amount']==='' && $fields['category']==='' && $fields['notes']==='' && !$fields['prefilled'],'intentionally cleared management fields stay cleared');
$fields=review_field_values($report,array_replace($ai,['currency'=>'USD']));
check($fields['amount']==='' && $fields['category']==='Fuel','non-AED suggestions are not silently treated as AED');
$fields=review_field_values($report,array_replace($ai,['amount'=>12.345]));
check($fields['amount']==='','do not silently round an invalid AI amount');
echo "Auto-approval and email content tests passed.\n";
