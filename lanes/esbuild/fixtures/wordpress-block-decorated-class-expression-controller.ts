import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json' with { type: 'json' };

const blockController = (config) => (value) => value;

export const DecoratedController = @blockController<BlockConfiguration>(metadata) class {
  static settings: BlockConfiguration = {
    supports: { html: false },
    viewScript: 'file:./view.js',
  };
  static [assetKey('worker')]: string = 'file:./card-worker.js';

  register() {
    wp.blocks.registerBlockType(metadata.name, DecoratedController.settings);
  }
};
