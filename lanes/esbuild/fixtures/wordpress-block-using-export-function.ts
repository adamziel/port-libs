import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript);

export function registerPreviewBlock() {
  wp.blocks.registerBlockType(metadata.name, {
    ...metadata,
    viewScript: previewAsset.url,
  });
}
