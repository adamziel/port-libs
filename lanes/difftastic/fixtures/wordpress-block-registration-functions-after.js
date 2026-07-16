function registerCardBlock() {
  wp.blocks.registerBlockType("acme/card", cardSettings);
  wp.i18n.setLocaleData(localeData, "acme");
}

function registerQueryBlock() {
  wp.blocks.registerBlockType("acme/query", querySettings);
}

function registerGalleryBlock() {
  wp.blocks.registerBlockType("acme/gallery", gallerySettings);
}
