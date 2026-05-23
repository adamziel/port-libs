import type { BlockConfiguration } from '@wordpress/blocks';

declare const assetKey: (name: string) => string;
declare const metadata: { name: string };
declare const wp: {
  blocks: {
    registerBlockType(name: string, config: BlockConfiguration): void;
  };
};

export default class {
  static settings: BlockConfiguration = {
    supports: { html: false },
    viewScript: 'file:./view.js',
  };
  static [assetKey('worker')]: string = 'file:./card-worker.js';

  register(): void {
    wp.blocks.registerBlockType(metadata.name, {
      supports: { html: false },
      viewScript: 'file:./view.js',
    });
  }
}
