const pluginBuild = '7fbbb20dddba4396';
const playgroundClientModuleUrl = 'https://playground.wordpress.net/client/index.js';

const iframe = document.getElementById('wp-playground');
const playgroundPanel = document.getElementById('playground-panel');
const form = document.getElementById('converter-form');
const fileInput = document.getElementById('file-input');
const formatInput = document.getElementById('format-input');
const titleInput = document.getElementById('title-input');
const convertButton = document.getElementById('convert-button');
const dropzone = document.getElementById('dropzone');
const fileName = document.getElementById('file-name');
const statusText = document.getElementById('status-text');
const statusDot = document.getElementById('playground-status');
const logOutput = document.getElementById('log-output');
const overlayTitle = document.getElementById('overlay-title');
const pageDropOverlay = document.getElementById('page-drop-overlay');

const formatByExtension = new Map(Object.entries({
  bib: 'bibtex',
  biblatex: 'biblatex',
  bits: 'bits',
  commonmark: 'commonmark',
  csljson: 'csljson',
  csv: 'csv',
  dbk: 'docbook',
  doc: 'doc',
  docbook: 'docbook',
  docx: 'docx',
  dokuwiki: 'dokuwiki',
  enl: 'endnotexml',
  endnote: 'endnotexml',
  endnotexml: 'endnotexml',
  epub: 'epub',
  fb2: 'fb2',
  gfm: 'gfm',
  htm: 'html',
  html: 'html',
  ipynb: 'ipynb',
  jats: 'jats',
  jira: 'jira',
  json: 'json',
  man: 'man',
  mdoc: 'mdoc',
  markdown: 'markdown',
  md: 'markdown',
  mediawiki: 'mediawiki',
  mw: 'mediawiki',
  native: 'native',
  odt: 'odt',
  opml: 'opml',
  pdf: 'pdf',
  pptx: 'pptx',
  ris: 'ris',
  rst: 'rst',
  rtf: 'rtf',
  tex: 'latex',
  tsv: 'tsv',
  txt: 'markdown',
  wiki: 'mediawiki',
  xlsx: 'xlsx',
  xml: 'xml',
}));

let playgroundClient = null;
let playgroundReady = false;
let playgroundBootPromise = null;
let startPlaygroundWeb = null;
let selectedFile = null;
let conversionActive = false;
let dragDepth = 0;

fileInput.addEventListener('change', async () => {
  const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
  setSelectedFile(file);
});

document.addEventListener('dragenter', (event) => {
  if (!hasDraggedFiles(event)) {
    return;
  }
  event.preventDefault();
  dragDepth += 1;
  setPageDragActive(true);
});

document.addEventListener('dragover', (event) => {
  if (!hasDraggedFiles(event)) {
    return;
  }
  event.preventDefault();
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = conversionActive ? 'none' : 'copy';
  }
  setPageDragActive(true);
});

document.addEventListener('dragleave', (event) => {
  if (!hasDraggedFiles(event)) {
    return;
  }
  event.preventDefault();
  dragDepth = Math.max(0, dragDepth - 1);
  if (dragDepth === 0) {
    setPageDragActive(false);
  }
});

document.addEventListener('drop', async (event) => {
  if (!hasDraggedFiles(event)) {
    return;
  }
  event.preventDefault();
  dragDepth = 0;
  setPageDragActive(false);
  const file = event.dataTransfer && event.dataTransfer.files.length > 0
    ? event.dataTransfer.files[0]
    : null;
  if (!file) {
    return;
  }
  setSelectedFile(file);
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  await convertSelectedFile();
});

async function convertSelectedFile() {
  if (!selectedFile) {
    return;
  }
  if (conversionActive) {
    log('A conversion is already running.');
    return;
  }

  setBusy(true);
  conversionActive = true;
  setOverlayTitle('Converting your document');
  setPlaygroundState('converting');
  try {
    await bootPlayground();
    log(`Reading ${selectedFile.name} (${formatBytes(selectedFile.size)})`);
    const payload = {
      filename: selectedFile.name,
      format: formatInput.value,
      title: titleInput.value,
      bytes: await readFileAsBase64(selectedFile),
    };

    setStatus('loading', 'Converting in WordPress Playground...');
    setOverlayTitle('Converting your document');
    setPlaygroundState('converting');
    const response = await playgroundClient.request({
      method: 'POST',
      url: '/wp-json/port-libs/v1/convert',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const text = typeof response.text === 'function' ? await response.text() : response.text;
    const data = JSON.parse(text);
    if (!data.ok) {
      throw new Error(data.message || 'Conversion failed.');
    }

    log(`Created page #${data.postId}: ${data.title}`);
    log(`Rendered image tags: ${data.imageTagCount}; imported media files: ${data.imagesImported}`);
    for (const diagnostic of data.diagnostics || []) {
      log(diagnostic);
    }
    const pagePath = playgroundPath(data.pageUrl);
    setOverlayTitle('Opening the page');
    await playgroundClient.goTo(pagePath);
    conversionActive = false;
    setPlaygroundState('ready');
    setStatus('ready', 'Page created and opened in Playground.');
  } catch (error) {
    conversionActive = false;
    setPlaygroundState(playgroundReady ? 'ready' : 'idle');
    setStatus('error', error instanceof Error ? error.message : String(error));
    log(error instanceof Error ? error.stack || error.message : String(error));
  } finally {
    setBusy(false);
  }
}

async function bootPlayground() {
  if (playgroundReady) {
    return;
  }
  if (playgroundBootPromise) {
    await playgroundBootPromise;
    return;
  }

  playgroundBootPromise = startPlayground();
  await playgroundBootPromise;
}

async function startPlayground() {
  try {
    const pluginUrl = new URL(`playground/port-libs-playground-converter.zip?v=${pluginBuild}`, window.location.href).href;
    setPlaygroundState(conversionActive ? 'converting' : 'loading');
    setStatus('loading', 'Starting WordPress Playground...');
    if (conversionActive) {
      setOverlayTitle('Starting WordPress');
    }
    log('Starting WordPress Playground');
    if (isLikelyIOS()) {
      log('iOS detected; using on-demand Playground startup to reduce memory pressure.');
    }
    if (!startPlaygroundWeb) {
      const playgroundModule = await import(playgroundClientModuleUrl);
      startPlaygroundWeb = playgroundModule.startPlaygroundWeb;
    }
    log(`Installing converter plugin from ${pluginUrl}`);

    playgroundClient = await startPlaygroundWeb({
      iframe,
      remoteUrl: 'https://playground.wordpress.net/remote.html',
      blueprint: {
        preferredVersions: {
          php: '8.3',
          wp: 'latest',
        },
        landingPage: '/',
        features: {
          networking: true,
        },
        steps: [
          { step: 'login' },
          {
            step: 'installPlugin',
            pluginData: {
              resource: 'url',
              url: pluginUrl,
            },
            options: {
              activate: true,
            },
          },
        ],
      },
    });
    await playgroundClient.isReady();
    playgroundReady = true;
    setPlaygroundState(conversionActive ? 'converting' : 'ready');
    setStatus('ready', 'WordPress Playground is ready.');
    updateConvertAvailability();
  } catch (error) {
    playgroundBootPromise = null;
    setPlaygroundState('idle');
    setStatus('error', 'WordPress Playground failed to start.');
    log(error instanceof Error ? error.stack || error.message : String(error));
    throw error;
  }
}

function setSelectedFile(file) {
  selectedFile = file;
  if (!file) {
    fileName.textContent = 'No file selected';
    updateConvertAvailability();
    return;
  }

  fileName.textContent = `${file.name} (${formatBytes(file.size)})`;
  titleInput.value = titleFromFilename(file.name);
  const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
  formatInput.value = formatByExtension.get(extension) || '';
  updateConvertAvailability();
}

function setBusy(busy) {
  convertButton.disabled = busy || !selectedFile;
  fileInput.disabled = busy;
  dropzone.dataset.disabled = busy ? 'true' : 'false';
  formatInput.disabled = busy;
  titleInput.disabled = busy;
  convertButton.textContent = busy ? 'Converting...' : convertButtonLabel();
}

function updateConvertAvailability() {
  convertButton.disabled = !selectedFile;
  convertButton.textContent = convertButtonLabel();
}

function convertButtonLabel() {
  return playgroundReady ? 'Convert and open page' : 'Start WordPress and convert';
}

function setStatus(state, text) {
  statusDot.dataset.state = state;
  statusText.textContent = text;
}

function setPlaygroundState(state) {
  playgroundPanel.dataset.state = state;
}

function setOverlayTitle(text) {
  overlayTitle.textContent = text;
}

function setPageDragActive(active) {
  document.body.dataset.dragging = active ? 'true' : 'false';
  pageDropOverlay.setAttribute('aria-hidden', active ? 'false' : 'true');
}

function hasDraggedFiles(event) {
  const types = event.dataTransfer ? Array.from(event.dataTransfer.types || []) : [];
  return types.includes('Files');
}

function log(message) {
  const stamp = new Date().toISOString().slice(11, 19);
  logOutput.textContent += `[${stamp}] ${message}\n`;
  logOutput.scrollTop = logOutput.scrollHeight;
}

function readFileAsBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.addEventListener('error', () => {
      reject(reader.error || new Error('The file could not be read.'));
    });
    reader.addEventListener('load', () => {
      const result = typeof reader.result === 'string' ? reader.result : '';
      const comma = result.indexOf(',');
      if (comma === -1) {
        reject(new Error('The file could not be encoded.'));
        return;
      }
      resolve(result.slice(comma + 1));
    });
    reader.readAsDataURL(file);
  });
}

function isLikelyIOS() {
  return /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function titleFromFilename(name) {
  const stem = name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').trim();
  return stem ? stem.replace(/\b\w/g, (letter) => letter.toUpperCase()) : 'Converted document';
}

function formatBytes(bytes) {
  if (bytes < 1024) {
    return `${bytes} B`;
  }
  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`;
  }

  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function playgroundPath(url) {
  try {
    const parsed = new URL(url);
    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return url || '/';
  }
}
