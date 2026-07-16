import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };

abstract class BaseController {
  abstract register(): void;
}

class CardBlockController extends BaseController {
  blockName: string = metadata.name;
  settings: BlockConfiguration = { supports: { html: false } };

  constructor(private blocks = wp.blocks, ready = false) {
    ready ||= super(metadata);
  }

  register(): void {
    this.blocks.registerBlockType(this.blockName, this.settings);
  }
}

new CardBlockController().register();
