document.addEventListener('click', (event) => {
  const source = event.target.closest('[data-source-toggle]');
  if (source) {
    const box = source.closest('.converted');
    const enabled = box.classList.toggle('source-mode');
    source.textContent = enabled ? 'Rendered view' : 'View source';
  }
});

const syncGroups = new Map();
let syncing = false;

function scrollState(scroller) {
  const maxTop = Math.max(0, scroller.scrollHeight - scroller.clientHeight);
  const maxLeft = Math.max(0, scroller.scrollWidth - scroller.clientWidth);

  return {
    top: maxTop === 0 ? 0 : scroller.scrollTop / maxTop,
    left: maxLeft === 0 ? 0 : scroller.scrollLeft / maxLeft,
  };
}

function applyScrollState(scroller, state) {
  const maxTop = Math.max(0, scroller.scrollHeight - scroller.clientHeight);
  const maxLeft = Math.max(0, scroller.scrollWidth - scroller.clientWidth);
  scroller.scrollTop = Math.round(maxTop * state.top);
  scroller.scrollLeft = Math.round(maxLeft * state.left);
}

function wireScroller(group, scroller) {
  if (!scroller || scroller.dataset.syncWired === 'true') {
    return;
  }
  scroller.dataset.syncWired = 'true';
  if (!syncGroups.has(group)) {
    syncGroups.set(group, new Set());
  }
  syncGroups.get(group).add(scroller);
  scroller.addEventListener('scroll', () => {
    if (syncing) {
      return;
    }
    syncing = true;
    const state = scrollState(scroller);
    for (const peer of syncGroups.get(group) || []) {
      if (peer !== scroller) {
        applyScrollState(peer, state);
      }
    }
    window.requestAnimationFrame(() => {
      syncing = false;
    });
  }, { passive: true });
}

function iframeScroller(frame) {
  try {
    const doc = frame.contentDocument;
    return doc ? (doc.scrollingElement || doc.documentElement || doc.body) : null;
  } catch (error) {
    return null;
  }
}

function wireSyncPane(pane) {
  const group = pane.getAttribute('data-sync-group');
  if (!group) {
    return;
  }
  if (pane.tagName === 'IFRAME') {
    const attach = () => wireScroller(group, iframeScroller(pane));
    pane.addEventListener('load', attach);
    attach();
    return;
  }
  wireScroller(group, pane);
}

document.querySelectorAll('[data-sync-group]').forEach(wireSyncPane);