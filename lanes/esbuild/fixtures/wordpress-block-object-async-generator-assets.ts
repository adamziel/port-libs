import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

export const previewAssets = {
  async *assets(queue): AsyncGenerator<any> {
    await using asset: AsyncDisposable = await queue.openNext(metadata.viewScript);
    yield { handle: asset.handle, url: asset.url };
  },
};

const settings = {
  name: metadata.name,
  viewScript: metadata.viewScript,
} satisfies BlockConfiguration;

export async function registerQueuedAssets(queue) {
  for await (const asset of previewAssets.assets(queue)) {
    registerAsset(asset.handle, asset.url);
  }

  wp.blocks.registerBlockType(settings.name, settings);
}
