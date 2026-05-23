import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };
const blockController = (settings: BlockConfiguration) => (controller: Function) => controller;

@blockController<BlockConfiguration>(metadata)
class DecoratedBlockController {
  static settings: BlockConfiguration = {
    supports: { html: false },
    viewScript: "file:./view.js",
  };

  register() {
    wp.blocks.registerBlockType(metadata.name, DecoratedBlockController.settings);
  }
}

new DecoratedBlockController().register();
