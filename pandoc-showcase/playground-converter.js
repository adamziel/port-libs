import { startPlaygroundWeb } from 'https://playground.wordpress.net/client/index.js';

const iframe = document.getElementById('wp-playground');
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

const formatByExtension = new Map(Object.entries({
  bib: 'bibtex',
  csv: 'csv',
  doc: 'doc',
  docbook: 'docbook',
  docx: 'docx',
  epub: 'epub',
  fb2: 'fb2',
  htm: 'html',
  html: 'html',
  ipynb: 'ipynb',
  jira: 'jira',
  json: 'json',
  man: 'man',
  md: 'markdown',
  mediawiki: 'mediawiki',
  native: 'native',
  odt: 'odt',
  opml: 'opml',
  pdf: 'pdf',
  pptx: 'pptx',
  ris: 'ris',
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
let selectedFile = null;

bootPlayground();

fileInput.addEventListener('change', () => {
  setSelectedFile(fileInput.files && fileInput.files[0] ? fileInput.files[0] : null);
});

for (const eventName of ['dragenter', 'dragover']) {
  dropzone.addEventListener(eventName, (event) => {
    event.preventDefault();
    dropzone.dataset.active = 'true';
  });
}

for (const eventName of ['dragleave', 'drop']) {
  dropzone.addEventListener(eventName, (event) => {
    event.preventDefault();
    dropzone.dataset.active = 'false';
  });
}

dropzone.addEventListener('drop', (event) => {
  const file = event.dataTransfer && event.dataTransfer.files.length > 0
    ? event.dataTransfer.files[0]
    : null;
  if (file) {
    fileInput.files = event.dataTransfer.files;
    setSelectedFile(file);
  }
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!selectedFile || !playgroundReady || !playgroundClient) {
    return;
  }

  setBusy(true);
  log(`Reading ${selectedFile.name} (${formatBytes(selectedFile.size)})`);
  try {
    const bytes = new Uint8Array(await selectedFile.arrayBuffer());
    const payload = {
      filename: selectedFile.name,
      format: formatInput.value,
      title: titleInput.value,
      bytes: bytesToBase64(bytes),
    };

    setStatus('loading', 'Converting in WordPress Playground...');
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
    await playgroundClient.goTo(pagePath);
    setStatus('ready', 'Page created and opened in Playground.');
  } catch (error) {
    setStatus('error', error instanceof Error ? error.message : String(error));
    log(error instanceof Error ? error.stack || error.message : String(error));
  } finally {
    setBusy(false);
  }
});

async function bootPlayground() {
  try {
    const pluginUrl = new URL('playground/port-libs-playground-converter.zip', window.location.href).href;
    log('Starting WordPress Playground');
    log(`Installing converter plugin from ${pluginUrl}`);

    playgroundClient = await startPlaygroundWeb({
      iframe,
      remoteUrl: 'https://playground.wordpress.net/remote.html',
      blueprint: {
        preferredVersions: {
          php: '8.3',
          wp: 'latest',
        },
        landingPage: '/wp-admin/',
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
    setStatus('ready', 'WordPress Playground is ready.');
    updateConvertAvailability();
  } catch (error) {
    setStatus('error', 'WordPress Playground failed to start.');
    log(error instanceof Error ? error.stack || error.message : String(error));
  }
}

function setSelectedFile(file) {
  selectedFile = file;
  if (!file) {
    fileName.textContent = 'or choose a file';
    updateConvertAvailability();
    return;
  }

  fileName.textContent = `${file.name} (${formatBytes(file.size)})`;
  if (!titleInput.value.trim()) {
    titleInput.value = titleFromFilename(file.name);
  }
  if (!formatInput.value) {
    const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
    formatInput.value = formatByExtension.get(extension) || '';
  }
  updateConvertAvailability();
}

function setBusy(busy) {
  convertButton.disabled = busy || !selectedFile || !playgroundReady;
  fileInput.disabled = busy;
  formatInput.disabled = busy;
  titleInput.disabled = busy;
  convertButton.textContent = busy ? 'Converting...' : 'Convert and open page';
}

function updateConvertAvailability() {
  convertButton.disabled = !selectedFile || !playgroundReady;
}

function setStatus(state, text) {
  statusDot.dataset.state = state;
  statusText.textContent = text;
}

function log(message) {
  const stamp = new Date().toISOString().slice(11, 19);
  logOutput.textContent += `[${stamp}] ${message}\n`;
  logOutput.scrollTop = logOutput.scrollHeight;
}

function bytesToBase64(bytes) {
  let binary = '';
  const chunkSize = 0x8000;
  for (let offset = 0; offset < bytes.length; offset += chunkSize) {
    const chunk = bytes.subarray(offset, offset + chunkSize);
    binary += String.fromCharCode(...chunk);
  }

  return btoa(binary);
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
