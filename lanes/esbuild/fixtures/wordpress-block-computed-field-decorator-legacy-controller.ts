import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const assetKey = (name: string) => 'wp:' + name;
const fieldController = (settings: BlockConfiguration) => (value: undefined) => value;

class ComputedFieldDecoratedRegistration {
  @fieldController<BlockConfiguration>(metadata)
  [assetKey('settings')]: BlockConfiguration = metadata;

  register(blocks = wp.blocks) {
    blocks.registerBlockType(metadata.name, this[assetKey('settings')]);
  }
}

new ComputedFieldDecoratedRegistration().register();
