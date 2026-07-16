import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const methodController = (settings: BlockConfiguration) => (method: Function) => method;

class ConstructorDecoratedRegistration {
  private blocks;

  constructor(blocks: typeof wp.blocks = wp.blocks) {
    this.blocks = blocks;
  }

  @methodController<BlockConfiguration>(metadata)
  register(settings: BlockConfiguration = metadata) {
    this.blocks.registerBlockType(metadata.name, settings);
  }
}

new ConstructorDecoratedRegistration().register();
