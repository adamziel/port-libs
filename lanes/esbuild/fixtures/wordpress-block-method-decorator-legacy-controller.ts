import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const methodController = (settings: BlockConfiguration) => (method: Function) => method;

class MethodDecoratedRegistration {
  @methodController<BlockConfiguration>(metadata)
  register(blocks = wp.blocks) {
    blocks.registerBlockType(metadata.name, metadata);
  }
}

new MethodDecoratedRegistration().register();
