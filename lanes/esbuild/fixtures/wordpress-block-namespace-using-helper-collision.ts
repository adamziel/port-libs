import type { BlockConfiguration } from '@wordpress/blocks';

const __knownSymbol = wp.symbols.known;
const __typeError = wp.errors.type;
const __using = wp.disposables.using;
const __callDispose = wp.disposables.callDispose;

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };

namespace CardBlockRuntime {
  export const settings = {
    name: metadata.name,
    viewScript: metadata.viewScript,
  };

  using previewAsset: Disposable = acquirePreviewAsset(settings.viewScript);

  export const previewUrl = previewAsset.url;

  wp.blocks.registerBlockType(settings.name, {
    ...settings,
    viewScript: previewUrl,
  });
}
