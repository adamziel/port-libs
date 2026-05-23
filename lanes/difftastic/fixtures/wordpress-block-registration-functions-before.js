function registerCardBlock() {
  wp.blocks.registerBlockType("acme/card", cardSettings);
}

function registerGalleryBlock() {
  wp.blocks.registerBlockType("acme/gallery", gallerySettings);
}
