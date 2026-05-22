if (window.wp) {
  registerBlockView('acme/card', {
    actions: ['toggle', 'share', 'dismiss'],
    hydrate: false,
  });
  hydrateCard({ expanded: true });
}
