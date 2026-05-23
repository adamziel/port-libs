import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

export const previewAssetStream = (async function* (queue): AsyncGenerator<any> {
  await using asset: AsyncDisposable = await queue.openNext(metadata.viewScript);
  yield { handle: asset.handle, url: asset.url };
  yield* queue.extraAssets(metadata.name);
});

const settings = {
  name: metadata.name,
  viewScript: metadata.viewScript,
} satisfies BlockConfiguration;

export async function registerStreamedAssets(queue) {
  for await (const asset of previewAssetStream(queue)) {
    registerAsset(asset.handle, asset.url);
  }

  wp.blocks.registerBlockType(settings.name, settings);
}
