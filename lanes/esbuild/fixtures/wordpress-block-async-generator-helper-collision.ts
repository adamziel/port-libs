import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

const __knownSymbol = wp.interactivity.knownSymbol;
const __typeError = wp.interactivity.typeError;
const __await = wp.interactivity.await;
const __asyncGenerator = wp.interactivity.asyncGenerator;
const __yieldStar = wp.interactivity.yieldStar;
const __forAwait = wp.interactivity.forAwait;

async function* streamInteractiveAssets(queue): AsyncGenerator<any> {
  await using asset: AsyncDisposable = await queue.openNext(metadata.viewScript);
  yield { handle: asset.handle, url: asset.url };
  yield* queue.extraAssets(metadata.name);

  for await (let followup of queue.followupAssets(metadata.name)) {
    yield followup;
  }
}

const settings = {
  name: metadata.name,
  viewScript: metadata.viewScript,
} satisfies BlockConfiguration;

export async function registerInteractiveAssets(queue) {
  for await (const asset of streamInteractiveAssets(queue)) {
    registerAsset(asset.handle, asset.url);
  }

  wp.blocks.registerBlockType(settings.name, settings);
}
