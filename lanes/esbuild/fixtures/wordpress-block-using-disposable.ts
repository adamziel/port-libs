import type { BlockConfiguration } from '@wordpress/blocks';

using previewAsset: Disposable = acquirePreviewAsset(metadata.viewScript),
  workerAsset: Disposable = acquireWorkerAsset("file:./card-worker.js");

const settings = {
  name: metadata.name,
  viewScript: previewAsset.url,
  worker: workerAsset.url,
} satisfies BlockConfiguration;

wp.blocks.registerBlockType(settings.name, settings);
