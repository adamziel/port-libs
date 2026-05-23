import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript);

export const { name: blockName } = metadata, settings = {
  name: blockName,
  viewScript: previewAsset.url,
} satisfies BlockConfiguration;

wp.blocks.registerBlockType(settings.name, settings);
