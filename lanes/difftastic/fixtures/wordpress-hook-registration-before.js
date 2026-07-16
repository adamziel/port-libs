wp.hooks.addAction('acme.card.init', 'acme/card', () => {
  hydrateCard();
});

wp.hooks.addFilter('blocks.registerBlockType', 'acme/card', settings => settings);
