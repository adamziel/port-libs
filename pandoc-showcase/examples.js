const catalogUrl = 'examples-index.json';
const viewLabels = {
  phpHtml: 'PHP HTML',
  wpBlocks: 'WordPress blocks',
  haskell: 'Pandoc HTML',
};

const formatFilter = document.getElementById('format-filter');
const examplePicker = document.getElementById('example-picker');
const loadButton = document.getElementById('load-example');
const previousButton = document.getElementById('previous-example');
const nextButton = document.getElementById('next-example');
const viewButtons = Array.from(document.querySelectorAll('[data-example-view]'));
const catalogSummary = document.getElementById('catalog-summary');
const formatBadge = document.getElementById('current-example-format');
const title = document.getElementById('current-example-title');
const description = document.getElementById('current-example-description');
const viewSize = document.getElementById('view-size');
const largeViewWarning = document.getElementById('large-view-warning');
const viewerStatus = document.getElementById('viewer-status');
const exampleLinks = document.getElementById('example-links');
const downloadSource = document.getElementById('download-source');
const sourceReference = document.getElementById('source-reference');
const openOutput = document.getElementById('open-output');
const openFullComparison = document.getElementById('open-full-comparison');
const frame = document.getElementById('example-frame');

const state = {
  catalog: null,
  examples: [],
  selectedId: '',
  view: 'phpHtml',
  loadedId: '',
  loadToken: 0,
};

function formatBytes(bytes) {
  if (!Number.isFinite(bytes) || bytes <= 0) {
    return 'unavailable';
  }
  if (bytes < 1024) {
    return bytes + ' B';
  }
  if (bytes < 1024 * 1024) {
    return (bytes / 1024).toFixed(bytes < 100 * 1024 ? 1 : 0) + ' KB';
  }
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function selectedExample() {
  return state.examples.find((example) => example.id === state.selectedId) || null;
}

function selectedView(example = selectedExample()) {
  return example && example.views ? example.views[state.view] || null : null;
}

function filteredExamples() {
  const format = formatFilter.value;
  return state.examples.filter((example) => !format || example.format === format);
}

function canAutoLoad(example) {
  const view = selectedView(example);
  return Boolean(view && view.ok && view.bytes > 0 && view.bytes <= state.catalog.automaticViewMaxBytes);
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

function populateFormats() {
  const selected = formatFilter.value;
  const formats = [...new Set(state.examples.map((example) => example.format).filter(Boolean))].sort();
  formatFilter.replaceChildren(createOption('', 'All formats'));
  formats.forEach((format) => formatFilter.append(createOption(format, format)));
  formatFilter.value = formats.includes(selected) ? selected : '';
}

function populateExamples(preferredId = state.selectedId) {
  const examples = filteredExamples();
  examplePicker.replaceChildren();
  examples.forEach((example) => {
    examplePicker.append(createOption(example.id, example.format + ' · ' + example.label));
  });

  if (examples.some((example) => example.id === preferredId)) {
    state.selectedId = preferredId;
  } else {
    state.selectedId = examples[0] ? examples[0].id : '';
  }
  examplePicker.value = state.selectedId;
  updateExampleDetails();
  updateControls();
}

function updateViewButtons() {
  viewButtons.forEach((button) => {
    const active = button.dataset.exampleView === state.view;
    button.setAttribute('aria-pressed', String(active));
  });
}

function updateExampleDetails() {
  const example = selectedExample();
  const view = selectedView(example);
  if (!example || !view) {
    formatBadge.textContent = 'No example available';
    title.textContent = 'No matching example';
    description.textContent = 'Try another format.';
    viewSize.textContent = '';
    exampleLinks.hidden = true;
    largeViewWarning.hidden = true;
    return;
  }

  formatBadge.textContent = example.format + ' · ' + viewLabels[state.view];
  title.textContent = example.label;
  description.textContent = example.description || example.source || 'No description is available.';
  viewSize.textContent = view.ok
    ? viewLabels[state.view] + ' · ' + formatBytes(view.bytes)
    : viewLabels[state.view] + ' unavailable';

  exampleLinks.hidden = false;
  downloadSource.href = example.samplePath || '#';
  downloadSource.hidden = !example.samplePath;
  openOutput.hidden = !view.ok || !view.path;
  if (view.ok && view.path) {
    openOutput.href = view.path;
  } else {
    openOutput.removeAttribute('href');
  }
  openFullComparison.href = 'index.html#' + encodeURIComponent(example.id);
  sourceReference.hidden = !example.sourceUrl;
  if (example.sourceUrl) {
    sourceReference.href = example.sourceUrl;
  } else {
    sourceReference.removeAttribute('href');
  }

  const isLarge = view.ok && view.bytes > state.catalog.automaticViewMaxBytes;
  largeViewWarning.hidden = !isLarge;
  if (isLarge) {
    largeViewWarning.textContent = 'This ' + formatBytes(view.bytes)
      + ' result is larger than the automatic mobile limit. It will only load when you press “Load selected example”.';
  }
}

function updateControls() {
  const ready = state.catalog !== null && filteredExamples().length > 0;
  const automaticExamples = ready ? filteredExamples().filter(canAutoLoad) : [];
  formatFilter.disabled = state.catalog === null;
  examplePicker.disabled = !ready;
  loadButton.disabled = !ready;
  previousButton.disabled = automaticExamples.length < 2;
  nextButton.disabled = automaticExamples.length < 2;
  viewButtons.forEach((button) => {
    button.disabled = state.catalog === null;
  });
  updateViewButtons();
}

function unloadCurrentExample() {
  state.loadToken += 1;
  state.loadedId = '';
  delete frame.dataset.loadedPath;
  frame.removeAttribute('src');
  frame.hidden = true;
}

function writeSelectionToUrl(example) {
  const url = new URL(window.location.href);
  url.searchParams.set('example', example.id);
  url.searchParams.set('view', state.view);
  window.history.replaceState(null, '', url);
}

function loadSelectedExample() {
  const example = selectedExample();
  const view = selectedView(example);
  if (!example || !view || !view.ok || !view.path) {
    setStatus('No ' + viewLabels[state.view] + ' result is available for this example.');
    return;
  }

  const token = state.loadToken + 1;
  state.loadToken = token;
  state.loadedId = example.id;
  frame.hidden = false;
  frame.loading = 'eager';
  frame.dataset.loadedPath = view.path;
  frame.removeAttribute('src');
  frame.src = 'about:blank';
  setStatus('Loading ' + example.label + '…');
  writeSelectionToUrl(example);

  window.requestAnimationFrame(() => {
    if (token !== state.loadToken) {
      return;
    }
    frame.src = view.path;
  });
}

function moveExample(direction) {
  const examples = filteredExamples().filter(canAutoLoad);
  if (examples.length === 0) {
    setStatus('No small enough result is available for this format and view.');
    return;
  }

  const current = examples.findIndex((example) => example.id === state.selectedId);
  const nextIndex = current < 0
    ? (direction > 0 ? 0 : examples.length - 1)
    : (current + direction + examples.length) % examples.length;
  state.selectedId = examples[nextIndex].id;
  examplePicker.value = state.selectedId;
  updateExampleDetails();
  updateControls();
  loadSelectedExample();
}

function syncSelectionFromUrl() {
  const params = new URL(window.location.href).searchParams;
  const requestedId = params.get('example');
  const requestedView = params.get('view');
  const requested = state.examples.find((example) => example.id === requestedId);
  if (requested) {
    formatFilter.value = requested.format;
    state.selectedId = requested.id;
  }
  if (requestedView && viewLabels[requestedView]) {
    state.view = requestedView;
  }
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
    state.catalog = catalog;
    state.examples = catalog.examples.filter((example) => example && example.id && example.views);
    state.selectedId = catalog.defaultExampleId || state.examples[0].id;
    populateFormats();
    syncSelectionFromUrl();
    populateExamples(state.selectedId);

    const automaticCount = state.examples.filter((example) => {
      const view = example.views.phpHtml;
      return view && view.ok && view.bytes > 0 && view.bytes <= catalog.automaticViewMaxBytes;
    }).length;
    catalogSummary.textContent = state.examples.length + ' examples are available. '
      + automaticCount + ' PHP-rendered examples are under the '
      + formatBytes(catalog.automaticViewMaxBytes) + ' automatic mobile limit.';
    setStatus('Choose an example, then press “Load selected example”.');
  } catch (error) {
    catalogSummary.textContent = 'The lightweight example catalogue could not be loaded.';
    setStatus('Try reloading this page.');
  }
}

formatFilter.addEventListener('change', () => {
  unloadCurrentExample();
  populateExamples();
  setStatus('Choose an example, then press “Load selected example”.');
});

examplePicker.addEventListener('change', () => {
  state.selectedId = examplePicker.value;
  unloadCurrentExample();
  updateExampleDetails();
  updateControls();
  setStatus('Example selected. Press “Load selected example” when ready.');
});

loadButton.addEventListener('click', loadSelectedExample);
previousButton.addEventListener('click', () => moveExample(-1));
nextButton.addEventListener('click', () => moveExample(1));

viewButtons.forEach((button) => {
  button.addEventListener('click', () => {
    const nextView = button.dataset.exampleView;
    if (!nextView || !viewLabels[nextView] || nextView === state.view) {
      return;
    }
    const reload = state.loadedId === state.selectedId;
    state.view = nextView;
    updateExampleDetails();
    updateControls();
    if (reload) {
      loadSelectedExample();
    } else {
      setStatus('View selected. Press “Load selected example” when ready.');
    }
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
