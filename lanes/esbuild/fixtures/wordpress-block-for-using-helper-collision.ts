import type { BlockConfiguration } from '@wordpress/blocks';

const __knownSymbol = wp.symbols.known;
const __typeError = wp.errors.type;
const __using = wp.disposables.using;
const __callDispose = wp.disposables.callDispose;

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };

for (using asset: Disposable of collectBlockAssets(metadata)) {
  registerAsset(asset.handle, asset.url);
}

const settings = {
  name: metadata.name,
  viewScript: metadata.viewScript,
} satisfies BlockConfiguration;

wp.blocks.registerBlockType(settings.name, settings);
