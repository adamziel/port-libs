wp.hooks.addAction('acme.card.analytics', 'acme/card', () => {
  trackCard();
});

wp.hooks.addAction('acme.card.init', 'acme/card', () => {
  hydrateCard();
  bindCard();
});

wp.hooks.addFilter('blocks.registerBlockType', 'acme/card', settings => settings);
