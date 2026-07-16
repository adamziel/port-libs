@blockTypes.declaration(() => 0)
export declare abstract class BlockController {
  accessor #view;
  register(blockName: string): void;
}

declare class EditorRegistry {
  @track(() => 0)
  accessor #instance;
}

const metadata = { name: 'port-libs/card' };

wp.blocks.registerBlockType(metadata.name, {
  supports: { html: false },
});
