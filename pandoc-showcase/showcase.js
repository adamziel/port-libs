document.addEventListener('click', (event) => {
  const tab = event.target.closest('[data-tab-target]');
  if (tab) {
    const box = tab.closest('.converted');
    const target = tab.getAttribute('data-tab-target');
    box.querySelectorAll('[data-tab-target]').forEach((button) => {
      button.setAttribute('aria-selected', button === tab ? 'true' : 'false');
    });
    box.querySelectorAll('.tab-panel').forEach((panel) => {
      panel.classList.toggle('active', panel.id === target);
    });
    box.classList.remove('source-mode');
  }

  const source = event.target.closest('[data-source-toggle]');
  if (source) {
    const box = source.closest('.converted');
    const enabled = box.classList.toggle('source-mode');
    source.textContent = enabled ? 'Rendered view' : 'View source';
  }
});