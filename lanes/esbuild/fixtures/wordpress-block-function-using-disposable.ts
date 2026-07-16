import type { BlockConfiguration } from '@wordpress/blocks';

export function registerPreviewAsset(metadata) {
  using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript);
  const settings = { name: metadata.name, viewScript: previewAsset.url };
  wp.blocks.registerBlockType(settings.name, settings);
}
