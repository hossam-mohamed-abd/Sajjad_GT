document.addEventListener('DOMContentLoaded', () => {
  const photos = document.getElementById('photos');
  const camera = document.getElementById('camera');
  const count = document.getElementById('file-count');
  const picker = document.getElementById('photo-picker');
  const preview = document.getElementById('photo-preview');
  const previewImages = document.getElementById('preview-images');
  const previewCount = document.getElementById('preview-count');
  const photoError = document.getElementById('photo-error');
  const changePhotos = document.getElementById('change-photos');
  const keepPhotos = document.getElementById('keep-photos');
  const fallbackSend = document.getElementById('fallback-send');
  const selectedPhotos = () => [...(camera?.files || []), ...(photos?.files || [])];
  const photoCount = () => selectedPhotos().length;
  let previewUrls = [];
  if (photos && camera && picker && preview && previewImages && previewCount && photoError && changePhotos && keepPhotos && fallbackSend) {
    // Keep the ordinary upload form usable if JavaScript is unavailable.
    fallbackSend.hidden = true;
    const sendButton = preview.querySelector('button[type="submit"]');
    const renderPhotos = () => {
      previewUrls.forEach(url => URL.revokeObjectURL(url));
      previewUrls = [];
      previewImages.replaceChildren();
      const files = selectedPhotos();
      const n = files.length;
      count.textContent = n ? `${n} photo${n === 1 ? '' : 's'} selected` : 'No photos selected';
      previewCount.textContent = `${n} photo${n === 1 ? '' : 's'} selected`;
      preview.hidden = n === 0;
      picker.hidden = n > 0;
      keepPhotos.hidden = true;
      let error = '';
      if (n > 5) error = 'Please choose no more than 5 photos.';
      else if (files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) error = 'Please choose JPG, PNG or WEBP photos.';
      else if (files.some(file => file.size > 5 * 1024 * 1024 || file.size === 0)) error = 'Each photo must be less than 5 MB.';
      photoError.textContent = error;
      photoError.hidden = !error;
      if (sendButton) sendButton.disabled = !!error;
      files.slice(0, 5).forEach((file, index) => {
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return;
        const image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        previewUrls.push(image.src);
        image.alt = `Selected photo ${index + 1}`;
        previewImages.appendChild(image);
      });
    };
    const onSelection = (selected, other) => {
      // Choosing a replacement replaces the earlier selection, never submits hidden old photos.
      if (selected.files.length) other.value = '';
      renderPhotos();
    };
    photos.addEventListener('change', () => onSelection(photos, camera));
    camera.addEventListener('change', () => onSelection(camera, photos));
    changePhotos.addEventListener('click', () => {
      picker.hidden = false;
      keepPhotos.hidden = false;
      picker.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    keepPhotos.addEventListener('click', () => {
      picker.hidden = true;
      keepPhotos.hidden = true;
    });
    renderPhotos();
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
    const button = workerForm.querySelector('#photo-preview button[type="submit"]') || workerForm.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; button.textContent = 'Sending…'; }
  });
});
