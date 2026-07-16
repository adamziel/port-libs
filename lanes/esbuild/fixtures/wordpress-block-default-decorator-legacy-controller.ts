import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const blockController = (settings: BlockConfiguration) => (controller: Function) => controller;

export default @blockController<BlockConfiguration>(metadata)
class {
  static settings: BlockConfiguration = {
    supports: { html: false },
    viewScript: "file:./view.js",
  };

  register(blocks = wp.blocks) {
    blocks.registerBlockType(metadata.name, metadata);
  }
}
