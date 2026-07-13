const catalogUrl = 'examples-index.json';
const viewLabels = {
  phpHtml: 'HTML',
  wpBlocks: 'WordPress Block markup',
  haskell: 'Pandoc baseline',
};
const defaultView = 'wpBlocks';

const examplePicker = document.getElementById('example-picker');
const previousButton = document.getElementById('previous-example');
const nextButton = document.getElementById('next-example');
const viewButtons = Array.from(document.querySelectorAll('[data-example-view]'));
const viewerStatus = document.getElementById('viewer-status');
const downloadSource = document.getElementById('download-source');
const frame = document.getElementById('example-frame');

const state = {
  examples: [],
  selectedId: '',
  view: defaultView,
  automaticViewMaxBytes: 0,
  loadToken: 0,
};

function selectedExample() {
  return state.examples.find((example) => example.id === state.selectedId) || null;
}

function selectedView(example = selectedExample()) {
  return example && example.views ? example.views[state.view] || null : null;
}

function isBrowsableView(view) {
  return Boolean(view && view.ok && view.path && view.bytes > 0
    && view.bytes <= state.automaticViewMaxBytes);
}

function browsableExamples() {
  return state.examples.filter((example) => isBrowsableView(example.views && example.views.phpHtml));
}

function setStatus(message) {
  viewerStatus.textContent = message;
}

function createOption(value, label) {
  const option = document.createElement('option');
  option.value = value;
  option.textContent = label;
  return option;
}

function ensureBrowsableView() {
  if (isBrowsableView(selectedView())) {
    return;
  }

  const example = selectedExample();
  for (const fallbackView of [defaultView, 'phpHtml', 'haskell']) {
    const view = example && example.views ? example.views[fallbackView] : null;
    if (isBrowsableView(view)) {
      state.view = fallbackView;
      return;
    }
  }
}

function populateExamples(preferredId = state.selectedId) {
  const examples = browsableExamples();
  examplePicker.replaceChildren();
  examples.forEach((example) => {
    examplePicker.append(createOption(example.id, example.format + ' · ' + example.label));
  });

  if (examples.some((example) => example.id === preferredId)) {
    state.selectedId = preferredId;
  } else {
    state.selectedId = examples[0] ? examples[0].id : '';
  }
  ensureBrowsableView();
  examplePicker.value = state.selectedId;
  updateDownloadSource();
  updateControls();
}

function updateViewButtons() {
  viewButtons.forEach((button) => {
    const active = button.dataset.exampleView === state.view;
    button.setAttribute('aria-pressed', String(active));
  });
}

function updateDownloadSource() {
  const example = selectedExample();
  if (!example || !example.samplePath) {
    downloadSource.hidden = true;
    downloadSource.removeAttribute('href');
    return;
  }
  downloadSource.href = example.samplePath;
  downloadSource.hidden = false;
}

function updateControls() {
  const examples = browsableExamples();
  const ready = examples.length > 0;
  const example = selectedExample();
  examplePicker.disabled = !ready;
  previousButton.disabled = examples.length < 2;
  nextButton.disabled = examples.length < 2;
  viewButtons.forEach((button) => {
    const view = example && example.views ? example.views[button.dataset.exampleView] : null;
    button.disabled = !ready || !isBrowsableView(view);
  });
  updateViewButtons();
}

function unloadCurrentExample() {
  state.loadToken += 1;
  delete frame.dataset.loadedPath;
  frame.removeAttribute('src');
  frame.hidden = true;
}

function loadSelectedExample() {
  const example = selectedExample();
  const view = selectedView(example);
  if (!example || !isBrowsableView(view)) {
    unloadCurrentExample();
    setStatus('No ' + viewLabels[state.view] + ' result is available for this example.');
    return;
  }

  const token = state.loadToken + 1;
  state.loadToken = token;
  frame.hidden = false;
  frame.loading = 'eager';
  frame.dataset.loadedPath = view.path;
  frame.removeAttribute('src');
  frame.src = 'about:blank';
  setStatus('Loading ' + example.label + '…');

  window.requestAnimationFrame(() => {
    if (token !== state.loadToken) {
      return;
    }
    frame.src = view.path;
  });
}

function moveExample(direction) {
  const examples = browsableExamples();
  if (examples.length === 0) {
    setStatus('No browsable example is available.');
    return;
  }

  const current = examples.findIndex((example) => example.id === state.selectedId);
  const nextIndex = current < 0
    ? (direction > 0 ? 0 : examples.length - 1)
    : (current + direction + examples.length) % examples.length;
  state.selectedId = examples[nextIndex].id;
  ensureBrowsableView();
  examplePicker.value = state.selectedId;
  updateDownloadSource();
  updateControls();
  loadSelectedExample();
}

async function initialize() {
  try {
    const response = await fetch(catalogUrl, { cache: 'no-store' });
    if (!response.ok) {
      throw new Error('catalogue request failed (' + response.status + ')');
    }
    const catalog = await response.json();
    if (!Array.isArray(catalog.examples) || catalog.examples.length === 0 || !Number.isFinite(catalog.automaticViewMaxBytes)) {
      throw new Error('catalogue payload is incomplete');
    }
    state.automaticViewMaxBytes = catalog.automaticViewMaxBytes;
    state.examples = catalog.examples.filter((example) => example && example.id && example.views);
    state.selectedId = catalog.defaultExampleId || state.examples[0].id;
    populateExamples(state.selectedId);
    loadSelectedExample();
  } catch (error) {
    setStatus('Try reloading this page.');
  }
}

examplePicker.addEventListener('change', () => {
  state.selectedId = examplePicker.value;
  ensureBrowsableView();
  updateDownloadSource();
  updateControls();
  loadSelectedExample();
});

previousButton.addEventListener('click', () => moveExample(-1));
nextButton.addEventListener('click', () => moveExample(1));

viewButtons.forEach((button) => {
  button.addEventListener('click', () => {
    const nextView = button.dataset.exampleView;
    if (!nextView || !viewLabels[nextView] || nextView === state.view) {
      return;
    }
    state.view = nextView;
    updateControls();
    loadSelectedExample();
  });
});

frame.addEventListener('load', () => {
  const example = selectedExample();
  const path = frame.dataset.loadedPath;
  if (!example || !path || frame.getAttribute('src') !== path) {
    return;
  }
  setStatus('Loaded ' + example.label + '.');
});

initialize();
