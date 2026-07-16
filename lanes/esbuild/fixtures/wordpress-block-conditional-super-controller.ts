import type { BlockConfiguration } from '@wordpress/blocks';

declare const previewMode: boolean;
const metadata = { name: 'port-libs/card' };

class BaseController {
  constructor(settings) {}
}

class CardBlockController extends BaseController {
  settings: BlockConfiguration = { supports: { html: false } };

  constructor(public blockName = metadata.name, private blocks = wp.blocks) {
    if (previewMode) super(metadata);
    else super({ name: blockName });
  }

  register() {
    this.blocks.registerBlockType(this.blockName, this.settings);
  }
}

new CardBlockController().register();
