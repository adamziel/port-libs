import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };

class CardBlockAccessorController {
  readonly accessor settings?: BlockConfiguration = {
    supports: { html: false },
    viewScript: "file:./view.js",
  };
  accessor [assetKey("worker")]!: string = "file:./card-worker.js";
  accessor #blockName: string = metadata.name;

  register() {
    wp.blocks.registerBlockType(this.#blockName, this.settings);
  }
}

new CardBlockAccessorController().register();
