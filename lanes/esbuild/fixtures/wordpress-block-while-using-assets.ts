import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

export async function registerQueuedAssets(queue) {
  while (queue.hasMore()) {
    await using asset: AsyncDisposable = await queue.openNext(metadata.viewScript);
    registerAsset(asset.handle, asset.url);
  }

  const settings = {
    name: metadata.name,
    viewScript: metadata.viewScript,
  } satisfies BlockConfiguration;

  wp.blocks.registerBlockType(settings.name, settings);
}
