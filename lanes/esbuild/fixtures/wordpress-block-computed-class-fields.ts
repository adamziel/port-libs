import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card' };

function assetKey(name: string) {
  return name;
}

class CardBlockAssets {
  [assetKey('viewScript')]: string = 'file:./view.js';
  static [assetKey('worker')]: string = 'file:./card-worker.js';

  register(config: BlockConfiguration = { viewScript: this[assetKey('viewScript')] }): void {
    wp.blocks.registerBlockType(metadata.name, config);
  }
}

new CardBlockAssets().register();
