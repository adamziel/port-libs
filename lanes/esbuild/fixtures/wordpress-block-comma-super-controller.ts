import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };

abstract class BaseController {
  constructor(public metadata: BlockConfiguration) {}
  abstract register(): void;
}

class CardBlockController extends BaseController {
  settings: BlockConfiguration = { supports: { html: false } };

  constructor(public blockName = metadata.name, private blocks = wp.blocks) {
    preparePreview(), preloadAssets(), super(metadata), hydrateAssets(), markReady();
  }

  register(): void {
    this.blocks.registerBlockType(this.blockName, this.settings);
  }
}

new CardBlockController().register();
