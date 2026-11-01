document.addEventListener('DOMContentLoaded', () => {
  const photos = document.getElementById('photos');
  const camera = document.getElementById('camera');
  const count = document.getElementById('file-count');
  const photoCount = () => (photos?.files.length || 0) + (camera?.files.length || 0);
  const updateCount = () => {
    const n = photoCount();
    count.textContent = n ? `${n} photo${n === 1 ? '' : 's'} selected` : 'No photos selected';
  };
  if (photos && camera && count) {
    photos.addEventListener('change', updateCount);
    camera.addEventListener('change', updateCount);
  }
  document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  }));
  // Audio is triggered by a direct interaction, so mobile browsers can play it.
  const workerForm = document.getElementById('worker-form');
  const audioEnabled = workerForm?.dataset.audioEnabled === '1';
  const activityInputs = audioEnabled ? document.querySelectorAll('#worker-form input[name="type"]') : [];
  const replay = document.getElementById('replay-activity');
  const audioStatus = document.getElementById('audio-status');
  const audioPaths = {
    expense: 'assets/audio/expense.mp3',
    installation: 'assets/audio/installation.mp3',
    delivery: 'assets/audio/delivery.mp3',
    receiving: 'assets/audio/receiving.mp3'
  };
  let activityAudio = null;
  function playActivity(value) {
    if (!audioEnabled || !audioPaths[value]) return;
    if (activityAudio) { activityAudio.pause(); activityAudio.currentTime = 0; }
    activityAudio = new Audio(audioPaths[value]);
    if (audioStatus) audioStatus.textContent = '';
    const playback = activityAudio.play();
    if (playback && playback.catch) playback.catch(() => {
      if (audioStatus) audioStatus.textContent = 'Turn on sound and tap Play again.';
    });
  }
  activityInputs.forEach(input => {
    input.addEventListener('change', () => {
      if (replay) replay.hidden = false;
      playActivity(input.value);
    });
  });
  if (audioEnabled && replay) replay.addEventListener('click', () => {
    const selected = document.querySelector('#worker-form input[name="type"]:checked');
    if (selected) playActivity(selected.value);
  });
  if (workerForm) workerForm.addEventListener('submit', event => {
    if (photoCount() < 1 || photoCount() > 5) { event.preventDefault(); window.alert('Please add between 1 and 5 photos.'); return; }
    const button = workerForm.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; button.textContent = 'Sending…'; }
  });
});
