import type { BlockConfiguration } from '@wordpress/blocks';

class BaseController {
  constructor(metadata) {
    this.metadata = metadata;
  }
}

class PrivateSettingsBlockController extends BaseController {
  #settings: BlockConfiguration = { supports: { html: false }, viewScript: 'file:./view.js' };

  constructor(public blockName = metadata.name, private blocks = wp.blocks) {
    super(metadata);
  }

  register() {
    this.blocks.registerBlockType(this.blockName, this.#settings);
  }
}

new PrivateSettingsBlockController().register();
