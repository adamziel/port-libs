import type { BlockConfiguration } from '@wordpress/blocks';

declare const metadata: { name: string };
declare const wp: {
  blocks: {
    registerBlockType(name: string, config: BlockConfiguration): void;
  };
};

class PrivateAccessorBlockController {
  #settings!: BlockConfiguration;

  get #blockSettings(): BlockConfiguration {
    return this.#settings;
  }

  set #blockSettings(value: BlockConfiguration) {
    this.#settings = value;
  }

  constructor() {
    this.#blockSettings = {
      supports: { html: false },
      viewScript: 'file:./view.js',
    };
  }

  register(): void {
    wp.blocks.registerBlockType(metadata.name, this.#blockSettings);
  }
}

new PrivateAccessorBlockController().register();
