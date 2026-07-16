const variations = [
    { name: 'card', scope: ['block', 'inserter'] },
    { name: 'gallery', scope: ['block'], isActive: ['layout'] },
    { name: 'media', scope: ['block', 'transform'] },
    { name: 'quote', scope: ['block'] },
];

wp.blocks.registerBlockVariation('acme/card', variations);
