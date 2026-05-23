import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const blockController = (settings: BlockConfiguration) => (controller: Function) => controller;
const methodController = (settings: BlockConfiguration) => (method: Function) => method;

@blockController<BlockConfiguration>(metadata)
class MixedDecoratedRegistration {
  @methodController<BlockConfiguration>(metadata)
  register(blocks = wp.blocks, settings: BlockConfiguration = metadata) {
    blocks.registerBlockType(metadata.name, settings);
  }
}

new MixedDecoratedRegistration().register();
