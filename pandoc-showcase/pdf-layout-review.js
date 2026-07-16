const payload = JSON.parse(document.querySelector('#pdf-layout-review-data')?.textContent || '{}');
const examples = Array.isArray(payload.examples) ? payload.examples : [];
if (examples.length < 1) throw new Error('The PDF layout reviewer has no examples.');

const root = document.querySelector('.reviewer');
const picker = document.querySelector('#review-picker');
const previous = document.querySelector('#review-previous');
const next = document.querySelector('#review-next');
const download = document.querySelector('#review-download');
const detail = document.querySelector('#review-detail');
const position = document.querySelector('#review-position');
const kind = document.querySelector('#review-kind');
const notes = document.querySelector('#review-notes');
const source = document.querySelector('#review-source');
const originalFrame = document.querySelector('#original-viewer');
const originalPages = document.querySelector('#original-pages');
const convertedFrame = document.querySelector('#converted-frame');
const originalStatus = document.querySelector('#original-status');
const convertedStatus = document.querySelector('#converted-status');
const criteriaList = document.querySelector('#quality-criteria');
const qualitySummary = document.querySelector('#quality-summary');
const viewButtons = [...document.querySelectorAll('[data-review-view]')];
const verdictButtons = [...document.querySelectorAll('[data-verdict]')];
const storagePrefix = 'port-libs:pdf-layout-review:';
let pdfjsLibraryPromise = null;
let originalLoadingTask = null;
let originalDocument = null;
let originalRenderGeneration = 0;
let activeIndex = 0;
let activeView = new URL(location.href).searchParams.get('view');
if (!['compare', 'converted', 'original'].includes(activeView)) {
  activeView = matchMedia('(max-width: 820px)').matches ? 'converted' : 'compare';
}

for (const example of examples) {
  const option = document.createElement('option');
  option.value = example.id;
  option.textContent = example.label;
  picker.append(option);
}

function selectedExample() { return examples[activeIndex]; }
function updateUrl() {
  const url = new URL(location.href);
  url.searchParams.set('example', selectedExample().id);
  url.searchParams.set('view', activeView);
  history.replaceState(null, '', url);
}
function setFramePath(frame, path) {
  if (frame.dataset.loadedPath === path) return;
  frame.dataset.loadedPath = path;
  frame.src = path;
}
function pdfjsLibrary() {
  if (!pdfjsLibraryPromise) {
    pdfjsLibraryPromise = import('./vendor/pdfjs/pdf.min.mjs').then((library) => {
      library.GlobalWorkerOptions.workerSrc = new URL('./vendor/pdfjs/pdf.worker.min.mjs', location.href).href;
      return library;
    });
  }
  return pdfjsLibraryPromise;
}
function cancelOriginalRender(clear = true) {
  originalRenderGeneration += 1;
  const loadingTask = originalLoadingTask;
  const pdfDocument = originalDocument;
  originalLoadingTask = null;
  originalDocument = null;
  if (loadingTask?.destroy) void loadingTask.destroy().catch(() => {});
  if (pdfDocument?.destroy) void pdfDocument.destroy().catch(() => {});
  delete originalFrame.dataset.requestedPath;
  delete originalFrame.dataset.loadedPath;
  if (clear) originalPages.replaceChildren();
}
async function renderOriginalPdf(example) {
  const path = example.samplePath;
  if (originalFrame.dataset.loadedPath === path && originalPages.querySelector('canvas')) return;
  if (originalFrame.dataset.requestedPath === path) return;
  cancelOriginalRender();
  const generation = originalRenderGeneration;
  originalFrame.dataset.requestedPath = path;
  originalStatus.textContent = 'Loading original';
  try {
    const library = await pdfjsLibrary();
    if (generation !== originalRenderGeneration) return;
    const base = new URL('.', location.href);
    const task = library.getDocument({
      url: new URL(path, base).href,
      cMapUrl: new URL('vendor/pdfjs/cmaps/', base).href,
      cMapPacked: true,
      standardFontDataUrl: new URL('vendor/pdfjs/standard_fonts/', base).href,
      wasmUrl: new URL('vendor/pdfjs/wasm/', base).href,
    });
    originalLoadingTask = task;
    const pdfDocument = await task.promise;
    if (generation !== originalRenderGeneration) {
      await pdfDocument.destroy();
      return;
    }
    originalLoadingTask = null;
    originalDocument = pdfDocument;
    for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber += 1) {
      if (generation !== originalRenderGeneration) return;
      originalStatus.textContent = `Rendering original page ${pageNumber} of ${pdfDocument.numPages}`;
      const page = await pdfDocument.getPage(pageNumber);
      const baseViewport = page.getViewport({ scale: 1 });
      const availableWidth = Math.max(240, originalFrame.clientWidth - 36);
      const cssScale = Math.min(1.45, availableWidth / Math.max(1, baseViewport.width));
      const outputScale = Math.min(2, Math.max(1, devicePixelRatio || 1));
      const viewport = page.getViewport({ scale: cssScale * outputScale });
      const canvas = document.createElement('canvas');
      canvas.className = 'original-page';
      canvas.width = Math.max(1, Math.ceil(viewport.width));
      canvas.height = Math.max(1, Math.ceil(viewport.height));
      canvas.style.width = `${viewport.width / outputScale}px`;
      canvas.style.height = `${viewport.height / outputScale}px`;
      canvas.setAttribute('aria-label', `Original PDF page ${pageNumber}`);
      originalPages.append(canvas);
      const context = canvas.getContext('2d', { alpha: false });
      if (!context) throw new Error('Canvas rendering is unavailable.');
      context.fillStyle = '#fff';
      context.fillRect(0, 0, canvas.width, canvas.height);
      await page.render({ canvasContext: context, viewport }).promise;
      page.cleanup();
    }
    if (generation !== originalRenderGeneration) return;
    originalFrame.dataset.loadedPath = path;
    originalStatus.textContent = `Original loaded · ${pdfDocument.numPages} page${pdfDocument.numPages === 1 ? '' : 's'}`;
  } catch (error) {
    if (generation !== originalRenderGeneration) return;
    delete originalFrame.dataset.requestedPath;
    delete originalFrame.dataset.loadedPath;
    originalPages.replaceChildren();
    const message = document.createElement('p');
    message.className = 'original-error';
    message.textContent = error instanceof Error ? error.message : String(error);
    originalPages.append(message);
    originalStatus.textContent = 'Could not render original';
  }
}
function unloadOriginalWhenHidden() {
  if (activeView !== 'converted') return;
  cancelOriginalRender();
  originalStatus.textContent = 'Not loaded in converted-only view';
}
function renderVerdict() {
  let verdict = '';
  try { verdict = localStorage.getItem(storagePrefix + selectedExample().id) || ''; } catch {}
  for (const button of verdictButtons) button.setAttribute('aria-pressed', String(button.dataset.verdict === verdict));
}
function setView(view, updateHistory = true) {
  if (!['compare', 'converted', 'original'].includes(view)) return;
  activeView = view;
  root.dataset.activeView = view;
  for (const button of viewButtons) button.setAttribute('aria-pressed', String(button.dataset.reviewView === view));
  const example = selectedExample();
  if (view !== 'converted') {
    void renderOriginalPdf(example);
  } else {
    unloadOriginalWhenHidden();
  }
  if (view !== 'original') setFramePath(convertedFrame, example.previewPath);
  if (updateHistory) updateUrl();
}
function criterionLabel(key, expected) {
  const labels = {
    minTextBytes: `At least ${expected} visible-text bytes`,
    minParagraphs: `At least ${expected} paragraphs`,
    minHeadings: `At least ${expected} headings`,
    minTables: `At least ${expected} tables`,
    maxTables: `No more than ${expected} tables`,
    minLists: `At least ${expected} lists`,
    minCodeBlocks: `At least ${expected} code blocks`,
    maxCodeBlocks: `No more than ${expected} code blocks`,
    minLineOrientedBlocks: `At least ${expected} line-oriented blocks`,
    maxLineOrientedBlocks: `No more than ${expected} line-oriented blocks`,
    minDialogueParagraphs: `At least ${expected} editable dialogue paragraphs`,
    maxSingleGlyphParagraphs: `No more than ${expected} single-glyph paragraphs`,
    requiredText: `Required text: ${(Array.isArray(expected) ? expected : [expected]).join(' · ')}`,
    orderedText: `Reading order: ${(Array.isArray(expected) ? expected : [expected]).join(' → ')}`,
    allowNoText: 'Image-only source may have no extracted text',
  };
  return labels[key] || `${key}: ${JSON.stringify(expected)}`;
}
function renderCriteria(results = null) {
  const success = selectedExample().success || {};
  criteriaList.replaceChildren();
  const entries = Object.entries(success);
  entries.push(['noSpacedGlyphRuns', true], ['noHorizontalOverflow', true], ['readablePdfFills', true]);
  const extraLabels = {
    noSpacedGlyphRuns: 'No sustained inter-glyph spacing',
    noHorizontalOverflow: 'No horizontal overflow',
    readablePdfFills: 'Readable text on PDF-derived fills',
  };
  for (const [key, expected] of entries) {
    const item = document.createElement('li');
    item.textContent = extraLabels[key] || criterionLabel(key, expected);
    item.dataset.status = results ? (results[key] ? 'pass' : 'fail') : 'pending';
    criteriaList.append(item);
  }
  if (!results) {
    qualitySummary.textContent = 'Waiting for preview';
    return;
  }
  const failed = Object.values(results).filter((passed) => !passed).length;
  qualitySummary.textContent = failed === 0 ? 'All automatic checks pass' : `${failed} automatic check${failed === 1 ? '' : 's'} need attention`;
  qualitySummary.style.color = failed === 0 ? 'var(--ok)' : 'var(--bad)';
}
function iframeMetrics(documentNode) {
  const bodyText = String(documentNode.body?.innerText || '').replace(/\s+/g, ' ').trim();
  const paragraphs = [...documentNode.querySelectorAll('p')];
  const compactLength = (value) => Array.from(String(value || '').replace(/\s+/gu, '')).length;
  const singleGlyphParagraphs = paragraphs.filter((node) => compactLength(node.textContent) === 1).length;
  const spacedGlyphPattern = /(?:^|[^\p{L}\p{M}])(?:[\p{L}\p{M}]\s+){4,}[\p{L}\p{M}](?=$|[^\p{L}\p{M}])/gu;
  let spacedGlyphRuns = 0;
  for (const node of documentNode.querySelectorAll('p,h1,h2,h3,h4,h5,h6,li,pre,td,th')) {
    spacedGlyphRuns += [...String(node.textContent || '').matchAll(spacedGlyphPattern)].length;
  }
  const rgb = (value) => {
    const match = String(value || '').match(/^rgba?\(\s*(\d+(?:\.\d+)?)\D+(\d+(?:\.\d+)?)\D+(\d+(?:\.\d+)?)/i);
    return match ? match.slice(1, 4).map(Number) : null;
  };
  const luminance = (channels) => channels.map((channel) => channel / 255).map((channel) => channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4).reduce((sum, channel, index) => sum + channel * [.2126, .7152, .0722][index], 0);
  let lowContrastPdfFills = 0;
  for (const node of documentNode.querySelectorAll('[data-pdf-fill-color]')) {
    if (!String(node.textContent || '').trim()) continue;
    const style = documentNode.defaultView?.getComputedStyle(node);
    if (!style) continue;
    const foreground = rgb(style.color);
    const background = rgb(style.backgroundColor);
    if (!foreground || !background) continue;
    const ratio = (Math.max(luminance(foreground), luminance(background)) + .05) / (Math.min(luminance(foreground), luminance(background)) + .05);
    if (ratio < 4.5) lowContrastPdfFills += 1;
  }
  return {
    bodyText,
    textBytes: new TextEncoder().encode(bodyText).length,
    paragraphs: paragraphs.length,
    headings: documentNode.querySelectorAll('h1,h2,h3,h4,h5,h6').length,
    tables: documentNode.querySelectorAll('table').length,
    lists: documentNode.querySelectorAll('ul,ol').length,
    codeBlocks: documentNode.querySelectorAll('pre.wp-block-code,.wp-block-code pre').length,
    lineOrientedBlocks: documentNode.querySelectorAll('pre.wp-block-verse,.wp-block-verse').length,
    dialogueParagraphs: documentNode.querySelectorAll('p > strong:first-child + br').length,
    singleGlyphParagraphs,
    spacedGlyphRuns,
    lowContrastPdfFills,
    horizontalOverflow: Math.max(0, (documentNode.documentElement?.scrollWidth || 0) - (documentNode.documentElement?.clientWidth || 0)),
  };
}
function evaluateCriteria(metrics) {
  const criteria = selectedExample().success || {};
  const result = {};
  const comparisons = {
    minTextBytes: (value) => metrics.textBytes >= value,
    minParagraphs: (value) => metrics.paragraphs >= value,
    minHeadings: (value) => metrics.headings >= value,
    minTables: (value) => metrics.tables >= value,
    maxTables: (value) => metrics.tables <= value,
    minLists: (value) => metrics.lists >= value,
    minCodeBlocks: (value) => metrics.codeBlocks >= value,
    maxCodeBlocks: (value) => metrics.codeBlocks <= value,
    minLineOrientedBlocks: (value) => metrics.lineOrientedBlocks >= value,
    maxLineOrientedBlocks: (value) => metrics.lineOrientedBlocks <= value,
    minDialogueParagraphs: (value) => metrics.dialogueParagraphs >= value,
    maxSingleGlyphParagraphs: (value) => metrics.singleGlyphParagraphs <= value,
    allowNoText: () => true,
    requiredText: (values) => values.every((value) => metrics.bodyText.includes(String(value))),
    orderedText: (values) => {
      let offset = -1;
      return values.every((value) => {
        offset = metrics.bodyText.indexOf(String(value), offset + 1);
        return offset >= 0;
      });
    },
  };
  for (const [key, expected] of Object.entries(criteria)) result[key] = comparisons[key] ? comparisons[key](expected) : true;
  result.noSpacedGlyphRuns = metrics.spacedGlyphRuns === 0;
  result.noHorizontalOverflow = metrics.horizontalOverflow <= 2;
  result.readablePdfFills = metrics.lowContrastPdfFills === 0;
  return result;
}
function selectExample(index, updateHistory = true) {
  activeIndex = (index + examples.length) % examples.length;
  const example = selectedExample();
  picker.value = example.id;
  position.textContent = `${activeIndex + 1} of ${examples.length} public corpus documents`;
  kind.textContent = example.kind;
  notes.textContent = example.notes;
  source.textContent = example.source;
  download.href = example.samplePath;
  download.download = example.samplePath.split('/').pop() || 'original.pdf';
  detail.href = `examples.html?example=${encodeURIComponent(example.id)}`;
  document.title = `${example.label} · PDF layout reviewer`;
  convertedStatus.textContent = 'Loading conversion';
  renderCriteria();
  setFramePath(convertedFrame, example.previewPath);
  if (activeView !== 'converted') {
    void renderOriginalPdf(example);
  } else {
    unloadOriginalWhenHidden();
  }
  renderVerdict();
  if (updateHistory) updateUrl();
}

picker.addEventListener('change', () => selectExample(examples.findIndex((example) => example.id === picker.value)));
previous.addEventListener('click', () => selectExample(activeIndex - 1));
next.addEventListener('click', () => selectExample(activeIndex + 1));
for (const button of viewButtons) button.addEventListener('click', () => setView(button.dataset.reviewView));
for (const button of verdictButtons) button.addEventListener('click', () => {
  const selected = button.getAttribute('aria-pressed') === 'true' ? '' : button.dataset.verdict;
  try {
    if (selected) localStorage.setItem(storagePrefix + selectedExample().id, selected);
    else localStorage.removeItem(storagePrefix + selectedExample().id);
  } catch {}
  renderVerdict();
});
convertedFrame.addEventListener('load', () => {
  if (convertedFrame.dataset.loadedPath !== selectedExample().previewPath) return;
  try {
    const metrics = iframeMetrics(convertedFrame.contentDocument);
    renderCriteria(evaluateCriteria(metrics));
    convertedStatus.textContent = `${metrics.textBytes.toLocaleString()} text bytes`;
  } catch (error) {
    convertedStatus.textContent = 'Could not inspect preview';
    qualitySummary.textContent = error instanceof Error ? error.message : String(error);
    qualitySummary.style.color = 'var(--bad)';
  }
});
addEventListener('keydown', (event) => {
  if (event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement || event.target instanceof HTMLTextAreaElement) return;
  if (event.key === 'ArrowLeft') selectExample(activeIndex - 1);
  if (event.key === 'ArrowRight') selectExample(activeIndex + 1);
});

const requestedId = new URL(location.href).searchParams.get('example');
const requestedIndex = examples.findIndex((example) => example.id === requestedId);
activeIndex = requestedIndex >= 0 ? requestedIndex : 0;
setView(activeView, false);
selectExample(activeIndex);
