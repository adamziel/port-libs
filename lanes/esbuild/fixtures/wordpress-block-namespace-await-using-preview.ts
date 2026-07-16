import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = {
  name: 'port-libs/card',
  viewScript: 'file:./view.js',
  editorScript: 'file:./editor.js',
};

namespace CardBlockRuntime {
  export const settings = {
    name: metadata.name,
    viewScript: metadata.viewScript,
    editorScript: metadata.editorScript,
  };

  export async function registerPreview(queue) {
    await using previewAsset: AsyncDisposable = await queue.open(settings.viewScript);

    wp.blocks.registerBlockType(settings.name, {
      ...settings,
      viewScript: previewAsset.url,
    });
  }
}
