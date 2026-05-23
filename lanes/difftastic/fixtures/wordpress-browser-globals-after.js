window.wp.blocks.registerBlockVariation('acme/card', {
    name: 'browser',
    title: document.title,
});
console.info('Registered card variation', module.hot, arguments.length);
export const boot = () => null;
