import type { BlockConfiguration } from '@wordpress/blocks';

const metadata = { name: 'port-libs/card', viewScript: 'file:./view.js' };
const methodKey = (name: string) => 'wp:' + name;
const methodController = (settings: BlockConfiguration) => (method: Function) => method;

class ComputedMethodDecoratedRegistration {
  @methodController<BlockConfiguration>(metadata)
  [methodKey('register')](blocks = wp.blocks): void {
    blocks.registerBlockType(metadata.name, metadata);
  }
}

new ComputedMethodDecoratedRegistration()[methodKey('register')]();
