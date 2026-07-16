namespace CardBlockRuntime {
  export import blocks = wp.blocks;

  export namespace Supports {
    export enum DisplayMode {
      Card,
      Grid = 3,
      List,
    }

    export const settings = {
      viewMode: DisplayMode.Card,
      layout: DisplayMode.Grid,
      fallback: DisplayMode.List,
    };
  }

  blocks.registerBlockType(metadata.name, Supports.settings);
}
