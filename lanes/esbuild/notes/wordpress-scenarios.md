# esbuild WordPress Scenario

Node-free asset tooling for shared hosting, Playground, PHAR tools, and browser-backed PHP environments.

## Current Native Slice

Native JavaScript lexer for identifiers, esbuild-style numeric literal forms, strings, hashbangs, comments, and punctuators. A small module analyzer maps static imports, expression/direct-string dynamic imports, exports, re-exports, import assertions/attributes, `import.meta` ESM markers, `new URL(..., import.meta.url)` asset references, TypeScript import-equals/export-equals/type-only import/export forms, TypeScript namespace/export namespace metadata, and package-vs-relative import classification.

The fixture in `fixtures/wordpress-block-view.js` represents a block view script that can be preflighted on shared hosting without Node/npm and classified as importing `@wordpress/dom-ready`, a relative `block.json` metadata import with `with { type: "json" }`, and relative stylesheet/worker assets. The fixture in `fixtures/wordpress-block-view-types.ts` represents a TypeScript block script that imports erased WordPress package types from `@wordpress/blocks` and `@wordpress/element`, keeps `@wordpress/dom-ready` and `./block.json` as runtime dependencies, and maps a legacy `import wpBlocks = wp.blocks` alias without shelling out to Node.
It also records the `CardBlock` namespace and its runtime exported `name`/`register` members so a PHP preflight can distinguish namespace-backed block registration code from erased TypeScript-only imports.

## Next Task

Map TypeScript import-use elision/tree-shaking behavior from `TestTSImportEquals` and adjacent import pruning cases, including side-effect-only downgrade for unused runtime imports.
