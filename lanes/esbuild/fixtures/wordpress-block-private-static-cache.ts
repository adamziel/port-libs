import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/private-cache' };

class CachedBlockController {
  static #settings: BlockConfiguration = { supports: { html: false } };
  static metadata: BlockConfiguration = metadata;

  static register(): void {
    wp.blocks.registerBlockType(metadata.name, CachedBlockController.#settings);
  }
}

CachedBlockController.register();
