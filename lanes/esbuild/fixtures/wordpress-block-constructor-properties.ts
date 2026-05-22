import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };

abstract class BaseController {
  abstract register(): void;
}

class CardBlockController extends BaseController {
  constructor(
    public blockName: string = metadata.name,
    private readonly blocks = wp.blocks,
  ) {
    super();
  }

  register(): void {
    this.blocks.registerBlockType(this.blockName, { supports: { html: false } });
  }
}

new CardBlockController().register();
