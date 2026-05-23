import type { BlockConfiguration } from '@wordpress/blocks';

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
