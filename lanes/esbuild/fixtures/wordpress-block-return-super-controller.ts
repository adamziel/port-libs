import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };

class BaseController {
  constructor(metadata) {
    this.metadata = metadata;
  }
}

class ReturnSuperBlockController extends BaseController {
  settings: BlockConfiguration = { supports: { html: false } };

  constructor(public blockName = metadata.name, private blocks = wp.blocks) {
    return preparePreview(), super(metadata), this;
  }

  register() {
    this.blocks.registerBlockType(this.blockName, this.settings);
  }
}

new ReturnSuperBlockController().register();
