import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const accessorKey = (name: string) => 'wp:' + name;
const accessorController = (settings: BlockConfiguration) => (value: Function) => value;

class ComputedAccessorDecoratedRegistration {
  #settings = metadata;

  @accessorController<BlockConfiguration>(metadata)
  get [accessorKey('settings')](): BlockConfiguration {
    return this.#settings;
  }

  @accessorController<BlockConfiguration>(metadata)
  set [accessorKey('settings')](value: BlockConfiguration) {
    this.#settings = value;
  }

  register(blocks = wp.blocks) {
    blocks.registerBlockType(metadata.name, this[accessorKey('settings')]);
  }
}

const controller = new ComputedAccessorDecoratedRegistration();
controller[accessorKey('settings')] = metadata;
controller.register();
