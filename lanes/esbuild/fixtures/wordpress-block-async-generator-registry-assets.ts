import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

const previewStreams = [
  async function* (queue): AsyncGenerator<any> {
    await using asset: AsyncDisposable = await queue.openNext(metadata.viewScript);
    yield { handle: asset.handle, url: asset.url };
    yield* queue.extraAssets(metadata.name);
  },
];

const previewStreamMap = {
  [metadata.name]: async function* (queue): AsyncGenerator<any> {
    await using asset: AsyncDisposable = await queue.openNext(metadata.editorScript);
    yield { handle: asset.handle, url: asset.url };
  },
};

const settings = {
  name: metadata.name,
  viewScript: metadata.viewScript,
} satisfies BlockConfiguration;

export async function registerStreamedAssets(queue) {
  for (const stream of previewStreams) {
    consumePreviewStream(metadata.name, async function* (): AsyncGenerator<any> {
      yield* stream(queue);
    });
  }

  consumePreviewStream(metadata.name, previewStreamMap[metadata.name]);
  wp.blocks.registerBlockType(settings.name, settings);
}
