import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };
const Edit = () => null;

class CardBlockController {
  declare metadata: BlockConfiguration;
  public declare static blockName: string;
  static declare supports = { html: false };

  register() {
    wp.blocks.registerBlockType(metadata.name, { edit: Edit });
  }
}

const controller = new CardBlockController();
controller.register();
