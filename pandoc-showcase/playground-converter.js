const pluginBuild = '9627568532d7b889';
const playgroundClientModuleUrl = 'https://playground.wordpress.net/client/index.js';

const iframe = document.getElementById('wp-playground');
const playgroundPanel = document.getElementById('playground-panel');
const form = document.getElementById('converter-form');
const fileInput = document.getElementById('file-input');
const directoryInput = document.getElementById('directory-input');
const formatInput = document.getElementById('format-input');
const titleInput = document.getElementById('title-input');
const imageModeInputs = Array.from(document.querySelectorAll('input[name="image-mode"]'));
const convertButton = document.getElementById('convert-button');
const dropzone = document.getElementById('dropzone');
const fileName = document.getElementById('file-name');
const statusText = document.getElementById('status-text');
const statusDot = document.getElementById('playground-status');
const logOutput = document.getElementById('log-output');
const conversionProgressText = document.getElementById('conversion-progress-text');
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
  zip: 'zip',
}));

let playgroundClient = null;
let playgroundReady = false;
let playgroundBootPromise = null;
let startPlaygroundWeb = null;
let selectedUpload = null;
let conversionActive = false;
let dragDepth = 0;

fileInput.addEventListener('change', async () => {
  const files = fileInput.files ? Array.from(fileInput.files) : [];
  setSelectedUpload(uploadFromFiles(files));
});

directoryInput.addEventListener('change', async () => {
  const files = directoryInput.files ? Array.from(directoryInput.files) : [];
  setSelectedUpload(uploadFromFiles(files, { forceBatch: true }));
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
  const upload = event.dataTransfer ? await uploadFromDataTransfer(event.dataTransfer) : null;
  if (!upload) {
    return;
  }
  setSelectedUpload(upload);
  await convertSelectedFile();
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  await convertSelectedFile();
});

async function convertSelectedFile() {
  if (!selectedUpload) {
    return;
  }
  if (conversionActive) {
    log('A conversion is already running.');
    return;
  }

  setBusy(true);
  conversionActive = true;
  setProgressStatus('Starting WordPress Playground...');
  setPlaygroundState(playgroundReady ? 'ready' : 'loading');
  try {
    await bootPlayground();
    setProgressStatus('Reading document...');
    log(`Reading ${selectedUpload.displayName} (${formatBytes(selectedUpload.totalSize)})`);
    const payload = await payloadFromUpload(selectedUpload);

    setStatus('loading', 'Converting in WordPress Playground...');
    setProgressStatus('Converting document in WordPress...');
    setPlaygroundState('ready');
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

    if (data.batch && Array.isArray(data.posts)) {
      log(`Created ${data.posts.length} page${data.posts.length === 1 ? '' : 's'} from ${selectedUpload.displayName}`);
      for (const post of data.posts) {
        log(`Created page #${post.postId}: ${post.title} (${post.path})`);
      }
    } else {
      log(`Created page #${data.postId}: ${data.title}`);
    }
    log(`Rendered image tags: ${data.imageTagCount}; imported media files: ${data.imagesImported}`);
    for (const diagnostic of data.diagnostics || []) {
      log(diagnostic);
    }
    const pagePath = playgroundPath(data.pageUrl);
    setProgressStatus('Opening converted page...');
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
    setPlaygroundState('loading');
    setStatus('loading', 'Starting WordPress Playground...');
    setProgressStatus('Starting WordPress Playground...');
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
    setPlaygroundState('ready');
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

function setSelectedUpload(upload) {
  selectedUpload = upload;
  if (!upload) {
    fileName.textContent = 'No file selected';
    titleInput.value = '';
    formatInput.value = '';
    updateConvertAvailability();
    return;
  }

  fileName.textContent = `${upload.displayName} (${formatBytes(upload.totalSize)})`;
  titleInput.value = upload.title;
  formatInput.value = upload.format;
  updateConvertAvailability();
}

function setBusy(busy) {
  form.dataset.busy = busy ? 'true' : 'false';
  convertButton.disabled = busy || !selectedUpload;
  fileInput.disabled = busy;
  directoryInput.disabled = busy;
  for (const input of imageModeInputs) {
    input.disabled = busy;
  }
  dropzone.dataset.disabled = busy ? 'true' : 'false';
  formatInput.disabled = busy;
  titleInput.disabled = busy;
  convertButton.textContent = busy ? 'Converting...' : convertButtonLabel();
}

function updateConvertAvailability() {
  convertButton.disabled = !selectedUpload;
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

function setProgressStatus(text) {
  conversionProgressText.textContent = text;
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

function uploadFromFiles(files, options = {}) {
  const entries = files
    .filter((file) => file && file.size > 0)
    .map((file) => ({
      file,
      path: normalizeRelativePath(file._plpcRelativePath || file.webkitRelativePath || file.name),
    }))
    .filter((entry) => entry.path);
  if (entries.length === 0) {
    return null;
  }

  const forceBatch = options.forceBatch || entries.length > 1 || entries.some((entry) => entry.path.includes('/'));
  if (!forceBatch && entries.length === 1) {
    const file = entries[0].file;
    const extension = extensionFromName(file.name);

    return {
      kind: 'single',
      displayName: file.name,
      title: titleFromFilename(file.name),
      format: formatByExtension.get(extension) || '',
      totalSize: file.size,
      entries,
    };
  }

  const root = commonRoot(entries.map((entry) => entry.path));
  const displayName = root || `${entries.length} files`;

  return {
    kind: 'collection',
    displayName,
    title: titleFromFilename(displayName),
    format: '',
    totalSize: entries.reduce((sum, entry) => sum + entry.file.size, 0),
    entries,
  };
}

async function uploadFromDataTransfer(dataTransfer) {
  const entries = Array.from(dataTransfer.items || [])
    .map((item) => (typeof item.webkitGetAsEntry === 'function' ? item.webkitGetAsEntry() : null))
    .filter(Boolean);
  if (entries.length > 0) {
    const files = [];
    for (const entry of entries) {
      await collectEntryFiles(entry, '', files);
    }
    return uploadFromFiles(files.map((item) => item.fileWithPath), {
      forceBatch: files.some((item) => item.path.includes('/')) || files.length > 1,
    });
  }

  return uploadFromFiles(Array.from(dataTransfer.files || []));
}

async function collectEntryFiles(entry, parentPath, files) {
  const path = normalizeRelativePath(parentPath ? `${parentPath}/${entry.name}` : entry.name);
  if (!path) {
    return;
  }
  if (entry.isFile) {
    const file = await fileFromEntry(entry);
    try {
      Object.defineProperty(file, '_plpcRelativePath', {
        value: path,
        configurable: true,
      });
    } catch {
      file._plpcRelativePath = path;
    }
    files.push({ path, fileWithPath: file });
    return;
  }
  if (!entry.isDirectory) {
    return;
  }

  const reader = entry.createReader();
  for (;;) {
    const children = await readDirectoryEntries(reader);
    if (children.length === 0) {
      break;
    }
    for (const child of children) {
      await collectEntryFiles(child, path, files);
    }
  }
}

function fileFromEntry(entry) {
  return new Promise((resolve, reject) => {
    entry.file(resolve, reject);
  });
}

function readDirectoryEntries(reader) {
  return new Promise((resolve, reject) => {
    reader.readEntries(resolve, reject);
  });
}

async function payloadFromUpload(upload) {
  if (upload.kind === 'single') {
    const entry = upload.entries[0];

    return {
      filename: entry.file.name,
      format: upload.format,
      title: upload.title,
      imageMode: selectedImageMode(),
      bytes: await readFileAsBase64(entry.file),
    };
  }

  return {
    filename: upload.displayName,
    title: upload.title,
    imageMode: selectedImageMode(),
    files: await Promise.all(upload.entries.map(async (entry) => ({
      path: entry.path,
      filename: entry.file.name,
      bytes: await readFileAsBase64(entry.file),
    }))),
  };
}

function selectedImageMode() {
  return imageModeInputs.find((input) => input.checked)?.value || 'important';
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
  const last = name.split('/').filter(Boolean).pop() || name;
  const stem = last.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').trim();
  return stem ? stem.replace(/\b\w/g, (letter) => letter.toUpperCase()) : 'Converted document';
}

function extensionFromName(name) {
  return name.includes('.') ? name.split('.').pop().toLowerCase() : '';
}

function normalizeRelativePath(path) {
  const parts = String(path || '')
    .replaceAll('\\', '/')
    .split('/')
    .filter((part) => part && part !== '.');
  const normalized = [];
  for (const part of parts) {
    if (part === '..') {
      normalized.pop();
    } else {
      normalized.push(part);
    }
  }

  return normalized.join('/');
}

function commonRoot(paths) {
  const roots = paths
    .map((path) => path.split('/').filter(Boolean)[0] || '')
    .filter(Boolean);
  if (roots.length === 0) {
    return '';
  }
  const first = roots[0];

  return roots.every((root) => root === first) ? first : '';
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
