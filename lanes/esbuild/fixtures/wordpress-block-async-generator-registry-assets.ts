import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from "./block.json" with { type: "json" };

const previewStreams = [
  async function* (queue): AsyncGenerator<any> {
    const resolvedHandle = await queue.resolveHandle?.(metadata.viewScript) ?? metadata.viewScript;
    const preferredHandle = await queue.resolveHandle(metadata.viewScript) || metadata.viewScript;
    await using asset: AsyncDisposable = await queue.openNext(resolvedHandle, metadata.name);
    yield { handle: preferredHandle, url: asset.url };
    yield* queue.extraAssets(metadata.name);
  },
  async function* (queue): AsyncGenerator<any> {
    await using fallback: AsyncDisposable = await (queue.prepareFallback(metadata.name), queue.openNext(metadata.editorScript, metadata.name));
    yield { handle: fallback.handle, url: fallback.url };
    yield* queue.fallbackAssets(metadata.name);
  },
];

const previewStreamMap = {
  [metadata.name]: async function* (queue): AsyncGenerator<any> {
    await using asset: AsyncDisposable = await queue.openNext(metadata.editorScript, metadata.name);
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
