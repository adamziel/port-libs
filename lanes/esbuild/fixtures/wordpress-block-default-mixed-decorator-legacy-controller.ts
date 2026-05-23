import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const blockController = (settings: BlockConfiguration) => (controller: Function) => controller;
const methodController = (settings: BlockConfiguration) => (method: Function) => method;

export default @blockController<BlockConfiguration>(metadata)
class {
  @methodController<BlockConfiguration>(metadata)
  register(blocks = wp.blocks, settings: BlockConfiguration = metadata) {
    blocks.registerBlockType(metadata.name, settings);
  }
}
