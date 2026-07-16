import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };

class CardBlockController {
  blockName: string = metadata.name;
  settings?: BlockConfiguration = { supports: { html: false } };
  static metadata: BlockConfiguration = metadata;

  register<T extends BlockConfiguration>(config: T = this.settings): void {
    wp.blocks.registerBlockType(this.blockName, config);
  }
}

new CardBlockController().register();
