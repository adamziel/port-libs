namespace PortLibs.CardBlock {
  export import blocks = wp.blocks;
  export const settings = { name: metadata.name, viewScript: 'file:./view.js' };

  blocks.registerBlockType(settings.name, settings);
}
