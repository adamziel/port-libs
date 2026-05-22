# esbuild WordPress Scenario

Node-free asset tooling for shared hosting, Playground, PHAR tools, and browser-backed PHP environments.

## Current Native Slice

Native JavaScript lexer for identifiers, esbuild-style numeric literal forms, strings, hashbangs, comments, and punctuators. A small module analyzer maps static imports, expression/direct-string dynamic imports, exports, re-exports, import assertions/attributes, `import.meta` ESM markers, `new URL(..., import.meta.url)` asset references, and package-vs-relative import classification. The fixture in `fixtures/wordpress-block-view.js` represents a block view script that can be preflighted on shared hosting without Node/npm and classified as importing `@wordpress/dom-ready`, a relative `block.json` metadata import with `with { type: "json" }`, and relative stylesheet/worker assets.

## Next Task

Map TypeScript import/export forms such as import equals, type-only imports/exports, and namespace exports.
