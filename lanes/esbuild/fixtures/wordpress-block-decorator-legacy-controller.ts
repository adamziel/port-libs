import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const blockController = (settings: BlockConfiguration) => (controller: Function) => controller;

@blockController<BlockConfiguration>(metadata)
class DecoratedRegistration {
  register() {
    wp.blocks.registerBlockType(metadata.name, metadata);
  }
}

new DecoratedRegistration().register();
