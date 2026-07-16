const blockRecord = [
  { name: metadata.name },
  { viewScript: 'file:./view.js' },
  ['card'],
];

namespace CardBlockRuntime {
  export import blocks = wp.blocks;
  export var [
    { name: blockName },
    settings,
    [viewMode],
  ] = blockRecord;

  blocks.registerBlockType(blockName, { ...settings, viewMode: viewMode });
}
