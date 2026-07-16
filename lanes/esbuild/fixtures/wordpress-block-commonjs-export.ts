const metadata = { name: 'port-libs/card' };

function registerBlock() {
  wp.blocks.registerBlockType(metadata.name, metadata);
}

export = registerBlock;
