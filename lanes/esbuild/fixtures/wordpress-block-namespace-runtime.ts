namespace CardBlockRuntime {
  export import blocks = wp.blocks;
  export const settings = { name: metadata.name, viewScript: 'file:./view.js' };

  export class PreviewController {}

  export function register() {
    blocks.registerBlockType(settings.name, settings);
  }

  register();
}
