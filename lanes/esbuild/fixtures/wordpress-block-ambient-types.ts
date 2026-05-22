declare module '@wordpress/blocks' {
  export interface BlockConfiguration {
    supports?: Record<string, unknown>;
  }

  export as namespace wpBlocks;
}

declare global {
  interface Window {
    wp: unknown;
  }
}

export as namespace wp;

const metadata = { name: 'port-libs/card' };

wp.blocks.registerBlockType(metadata.name, {
  supports: { html: false },
});
