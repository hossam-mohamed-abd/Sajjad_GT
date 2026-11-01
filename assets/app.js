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
  const workerForm = document.getElementById('worker-form');
  if (workerForm) workerForm.addEventListener('submit', event => {
    if (photoCount() < 1 || photoCount() > 5) { event.preventDefault(); window.alert('Please add between 1 and 5 photos.'); return; }
    const button = workerForm.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; button.textContent = 'Sending…'; }
  });
});
