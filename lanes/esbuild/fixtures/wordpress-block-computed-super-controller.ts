import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };

function assetKey(name: string) {
  return name;
}

function resolveBaseController(config) {
  return class BaseController {
    constructor(metadata) {
      this.metadata = metadata;
    }
  };
}

class CardBlockComputedController extends resolveBaseController(metadata) {
  [assetKey('settings')]: BlockConfiguration = { supports: { html: false }, viewScript: 'file:./view.js' };

  constructor(public blockName = metadata.name, private blocks = wp.blocks) {
    super(metadata);
  }

  register() {
    this.blocks.registerBlockType(this.blockName, this[assetKey('settings')]);
  }
}

new CardBlockComputedController().register();
