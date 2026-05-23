import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

const settings = {
  name: metadata.name,
  viewScript: metadata.viewScript,
} satisfies BlockConfiguration;

export default (async function* (queue): AsyncGenerator<any> {
  await using asset: AsyncDisposable = await queue.openNext(settings.viewScript);
  yield { handle: asset.handle, url: asset.url };
  yield* queue.extraAssets(settings.name);
});
