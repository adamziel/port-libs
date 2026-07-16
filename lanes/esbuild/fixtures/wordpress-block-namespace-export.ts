namespace CardBlockRuntime {
  export import blocks = wp.blocks;
  export const settings = { name: metadata.name, viewScript: 'file:./view.js' };
  export let viewMode = 'card';

  blocks.registerBlockType(settings.name, { viewMode: viewMode });
}
