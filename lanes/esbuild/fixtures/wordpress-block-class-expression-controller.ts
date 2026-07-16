import type { BlockConfiguration } from '@wordpress/blocks';

declare const BaseController: new (metadata: { name: string }) => {
  register(): void;
};
declare const assetKey: (name: string) => string;
declare const metadata: { name: string };
declare const wp: {
  blocks: {
    registerBlockType(name: string, config: BlockConfiguration): void;
  };
};

const Controller = class extends BaseController {
  static settings: BlockConfiguration = {
    supports: { html: false },
    viewScript: 'file:./view.js',
  };
  static [assetKey('worker')]: string = 'file:./card-worker.js';
  blockName: string = metadata.name;

  constructor() {
    super(metadata);
  }

  register(): void {
    wp.blocks.registerBlockType(this.blockName, Controller.settings);
  }
};

export { Controller as CardBlockController };
