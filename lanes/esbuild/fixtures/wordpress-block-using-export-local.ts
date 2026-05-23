import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript);

export const settings: BlockConfiguration = {
  name: metadata.name,
  viewScript: previewAsset.url,
};

export class PreviewRegistration {
  register() {
    wp.blocks.registerBlockType(settings.name, settings);
  }
}

new PreviewRegistration().register();
