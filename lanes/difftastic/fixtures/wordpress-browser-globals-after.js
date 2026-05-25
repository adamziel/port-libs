window.wp.blocks.registerBlockVariation('acme/card', {
    name: 'browser',
    title: document.title,
});
console.info('Registered card variation', module.hot, arguments.length);
class BlockPreview extends HTMLElement {
    connectedCallback() {
        this.dataset.ready = document.readyState;
        super.connectedCallback?.();
    }
}
export const boot = () => null;
