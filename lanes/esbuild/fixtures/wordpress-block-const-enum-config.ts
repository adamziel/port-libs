import type { BlockConfiguration } from '@wordpress/blocks';

const enum DisplayMode {
  Card,
  Grid = 3,
  List,
}

const metadata = { name: 'port-libs/card' };
const config = {
  viewMode: DisplayMode.Card,
  supports: {
    layout: DisplayMode.Grid,
    fallback: DisplayMode['List'],
  },
} satisfies BlockConfiguration;

wp.blocks.registerBlockType(metadata.name, config);
