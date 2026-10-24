document.querySelectorAll('[data-location-selects]').forEach(group => {
  const district = group.querySelector('[data-location-district]');
  const purok = group.querySelector('[data-location-purok]');
  const options = Array.from(purok.options).map(option => option.cloneNode(true));
  const chairman = group.querySelector('[data-location-chairman]');
  const updateChairman = () => {
    if (chairman) chairman.textContent = 'Purok chairman: ' + (purok.value ? (Array.from(purok.options).find(option => option.value === purok.value)?.dataset.chairman || 'Not yet assigned') : 'Select a purok');
  };
  purok.addEventListener('change', updateChairman);
  updateChairman();
  const updatePuroks = (initial = false) => {
    const previous = purok.value;
    purok.replaceChildren(...options.filter(option => !option.value || option.value === 'unassigned' || (initial && option.value === previous) || !Number(option.dataset.district) || !district.value || option.dataset.district === district.value).map(option => option.cloneNode(true)));
    purok.value = Array.from(purok.options).some(option => option.value === previous) ? previous : '';
    purok.dispatchEvent(new Event('change', { bubbles: true }));
  };
  district.addEventListener('change', () => updatePuroks());
  updatePuroks(true);
});
