import type { BlockConfiguration } from '@wordpress/blocks';

enum DisplayMode {
  Card,
  Grid = 3,
  List,
}

const metadata = { name: 'port-libs/card' };
const config = {
  viewMode: DisplayMode.Card,
  supports: {
    layout: DisplayMode.Grid,
  },
} satisfies BlockConfiguration;

wp.blocks.registerBlockType(metadata.name, config);
