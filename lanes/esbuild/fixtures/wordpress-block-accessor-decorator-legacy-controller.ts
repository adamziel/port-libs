import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const accessorController = (settings: BlockConfiguration) => (value: undefined) => value;

class AccessorDecoratedRegistration {
  @accessorController<BlockConfiguration>(metadata)
  accessor settings: BlockConfiguration = metadata;

  register(blocks = wp.blocks) {
    blocks.registerBlockType(metadata.name, this.settings);
  }
}

new AccessorDecoratedRegistration().register();
