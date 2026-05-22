import type { BlockConfiguration, BlockEditProps as EditProps } from '@wordpress/blocks';
import type { WPElement } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import blockMeta from './block.json' with { type: 'json' };
import wpBlocks = wp.blocks;

export type { BlockConfiguration as CardBlockConfiguration } from '@wordpress/blocks';
export { type EditProps, type WPElement };

const config = blockMeta;

namespace CardBlock {
  export const name = config.name;
  export function register() {
    wpBlocks.registerBlockType(name, config);
  }
}

domReady(() => {
  CardBlock.register();
});
