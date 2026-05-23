import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

export async function registerPreviewAssetByMode(mode, queue) {
  switch (mode) {
    case "view": {
      using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript);
      registerAsset(previewAsset.handle, previewAsset.url);
      break;
    }
    case "bulk":
      for (using modeAsset: Disposable of collectModeAssets(metadata, mode)) {
        registerAsset(modeAsset.handle, modeAsset.url);
      }
      break;
    default: {
      await using editorAsset: AsyncDisposable = await queue.openNext(metadata.editorScript);
      registerAsset(editorAsset.handle, editorAsset.url);
    }
  }

  const settings = {
    name: metadata.name,
    viewScript: metadata.viewScript,
  } satisfies BlockConfiguration;

  wp.blocks.registerBlockType(settings.name, settings);
}
