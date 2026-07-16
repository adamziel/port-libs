import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const fieldController = (settings: BlockConfiguration) => (value: undefined) => value;

class StaticFieldDecoratedRegistration {
  @fieldController<BlockConfiguration>(metadata)
  static settings: BlockConfiguration = metadata;

  static register(blocks = wp.blocks) {
    blocks.registerBlockType(metadata.name, StaticFieldDecoratedRegistration.settings);
  }
}

StaticFieldDecoratedRegistration.register();
