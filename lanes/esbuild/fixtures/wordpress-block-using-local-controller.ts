import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript);

class PreviewBlockController {
  static controller = PreviewBlockController;

  register() {
    wp.blocks.registerBlockType(metadata.name, {
      ...metadata,
      viewScript: previewAsset.url,
    } satisfies BlockConfiguration);
  }
}

new PreviewBlockController().register();

export { PreviewBlockController };
