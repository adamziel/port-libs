self.addEventListener('message', (event) => {
  self.postMessage({ ok: true, blockName: event.data?.blockName ?? null });
});
