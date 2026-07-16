const metadata = { name: 'port-libs/card' };

function registerBlock() {
  wp.blocks.registerBlockType(registerBlock.settings.name, registerBlock.settings);
}

namespace registerBlock {
  export const settings = { name: metadata.name, viewScript: 'file:./view.js' };
}

registerBlock();
