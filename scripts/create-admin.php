<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require dirname(__DIR__) . '/includes/bootstrap.php';
echo "Administrator name: "; $name = trim((string)fgets(STDIN));
echo "Administrator email: "; $email = trim((string)fgets(STDIN));
echo "Password (12 or more characters; input is visible): "; $password = rtrim((string)fgets(STDIN), "\r\n");
if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) { fwrite(STDERR, "Invalid name, email or password.\n"); exit(1); }
try { $q = db()->prepare("INSERT INTO users (name,email,role,password_hash) VALUES (?,?,'admin',?)"); $q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]); echo "Administrator created.\n"; }
catch (Throwable $e) { fwrite(STDERR, "Could not create administrator. Check database configuration and whether the email already exists.\n"); exit(1); }
