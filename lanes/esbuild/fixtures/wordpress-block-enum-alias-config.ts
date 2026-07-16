import type { BlockConfiguration } from '@wordpress/blocks';

enum DisplayMode {
  Card,
  Grid = 3,
  Default = Card,
  Wide = DisplayMode.Grid,
}

const metadata = { name: 'port-libs/card' };
const config = {
  viewMode: DisplayMode.Default,
  supports: {
    layout: DisplayMode.Wide,
    fallback: DisplayMode.Card,
  },
} satisfies BlockConfiguration;

wp.blocks.registerBlockType(metadata.name, config);
