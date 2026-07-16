registerBlockView('acme/card', {
  actions: ['toggle', 'dismiss'],
  hydrate: true,
});
hydrateCard({ expanded: false });
