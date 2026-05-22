# esbuild WordPress Scenario

Node-free asset tooling for shared hosting, Playground, PHAR tools, and browser-backed PHP environments.

## Current Native Slice

Native JavaScript lexer for identifiers, esbuild-style numeric literal forms, strings, hashbangs, comments, and punctuators. A small module analyzer maps static imports, expression/direct-string dynamic imports, exports, re-exports, import assertions/attributes, `import.meta` ESM markers, `new URL(..., import.meta.url)` asset references, TypeScript import-equals/export-equals/type-only import/export forms, TypeScript namespace/export namespace metadata, TypeScript import pruning metadata, and package-vs-relative import classification. A narrow namespace lowerer maps selected `TestTSImportEqualsInNamespace` printer behavior for import-equals aliases inside namespace blocks. A narrow module lowerer maps selected top-level `TestTSExportEquals`/`TestTSImportEquals` printer behavior for `export =`, `import =`, and `export import =` declarations, plus selected `TestTSTypes` erasure for type annotations, `as`, `satisfies`, and type-only imports.

The fixture in `fixtures/wordpress-block-view.js` represents a block view script that can be preflighted on shared hosting without Node/npm and classified as importing `@wordpress/dom-ready`, a relative `block.json` metadata import with `with { type: "json" }`, and relative stylesheet/worker assets. The fixture in `fixtures/wordpress-block-view-types.ts` represents a TypeScript block script that imports erased WordPress package types from `@wordpress/blocks` and `@wordpress/element`, keeps `@wordpress/dom-ready`, `./block.json`, and a legacy `import wpBlocks = wp.blocks` alias as pruned runtime dependencies, and maps the alias without shelling out to Node.
It also records the `CardBlock` namespace and its runtime exported `name`/`register` members so a PHP preflight can distinguish namespace-backed block registration code from erased TypeScript-only imports.
The namespace-lowering scenario maps a WordPress `export import blocks = wp.blocks` alias to `CardBlockRuntime.blocks = wp.blocks` and rewrites later alias uses to the namespace property, which is the shape needed before a future native printer can emit shared-hosting-compatible block registration code.
The fixture in `fixtures/wordpress-block-commonjs-export.ts` represents a legacy TypeScript block runtime that uses `export = registerBlock`. The module lowerer maps that to `module.exports = registerBlock`, giving PHP preflight tools a native CommonJS boundary without shelling out to esbuild or Node.
The fixture in `fixtures/wordpress-block-typed-callback.ts` represents a typed block edit callback using `BlockEditProps`, `WPElement`, and `satisfies BlockConfiguration`. The module lowerer erases those type-only imports and annotations while preserving the runtime `wp.element.createElement` and `wp.blocks.registerBlockType` calls.

## Next Task

Map the next bounded TypeScript printer slice, such as selected enum/namespace value export lowering, while keeping analyzer metadata separate from broader JS printing.
