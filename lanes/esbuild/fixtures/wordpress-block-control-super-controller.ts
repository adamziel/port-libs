import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };
let asset = null;

class BaseController {
  constructor(metadata) {
    this.metadata = metadata;
  }
}

class SwitchModeBlockController extends BaseController {
  settings: BlockConfiguration = { supports: { html: false } };

  constructor(public blockName = metadata.name, private blocks = wp.blocks) {
    switch (preparePreview(), super(metadata), resolveMode()) {
      case 'preview':
        preloadPreview();
        break;
      default:
        hydrateDefaults();
    }
  }

  register() {
    this.blocks.registerBlockType(this.blockName, this.settings);
  }
}

class ForAssetBlockController extends BaseController {
  settings: BlockConfiguration = { supports: { html: false }, viewScript: 'file:./view.js' };

  constructor(public blockName = metadata.name, private blocks = wp.blocks) {
    for (queueAssets(), super(metadata), asset = nextAsset(); asset; asset = nextAsset()) hydrateAsset(asset);
  }

  register() {
    this.blocks.registerBlockType(this.blockName, this.settings);
  }
}

new SwitchModeBlockController().register();
new ForAssetBlockController().register();
