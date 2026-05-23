import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const privateAccessorController = (settings: BlockConfiguration) => (value: undefined) => value;

class PrivateAccessorDecoratedRegistration {
  @privateAccessorController<BlockConfiguration>(metadata)
  accessor #settings: BlockConfiguration = metadata;

  register(blocks = wp.blocks) {
    blocks.registerBlockType(metadata.name, metadata);
  }
}

new PrivateAccessorDecoratedRegistration().register();
