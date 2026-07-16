import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const privateGetterController = (settings: BlockConfiguration) => (value: undefined) => value;

class PrivateGetterDecoratedRegistration {
  settings: BlockConfiguration = metadata;

  @privateGetterController<BlockConfiguration>(metadata)
  get #settings(): BlockConfiguration {
    return this.settings;
  }

  register(blocks = wp.blocks) {
    blocks.registerBlockType(metadata.name, this.settings);
  }
}

new PrivateGetterDecoratedRegistration().register();
