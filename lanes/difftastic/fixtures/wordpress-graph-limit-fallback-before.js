const variations = [
    { name: 'card', scope: ['block', 'inserter'] },
    { name: 'media', scope: ['block'] },
    { name: 'quote', scope: ['block'] },
];

wp.blocks.registerBlockVariation('acme/card', variations);
