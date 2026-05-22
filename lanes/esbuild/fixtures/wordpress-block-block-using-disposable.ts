import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };

if (metadata.viewScript) {
  using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript);
  queueAsset(previewAsset.url);
}

const settings = {
  name: metadata.name,
  viewScript: metadata.viewScript,
} satisfies BlockConfiguration;

wp.blocks.registerBlockType(settings.name, settings);
