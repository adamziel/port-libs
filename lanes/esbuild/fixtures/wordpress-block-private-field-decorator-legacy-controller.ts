import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const privateFieldController = (settings: BlockConfiguration) => (value: undefined) => value;

class PrivateFieldDecoratedRegistration {
  @privateFieldController<BlockConfiguration>(metadata)
  #settings: BlockConfiguration = metadata;

  register(blocks = wp.blocks) {
    blocks.registerBlockType(metadata.name, metadata);
  }
}

new PrivateFieldDecoratedRegistration().register();
