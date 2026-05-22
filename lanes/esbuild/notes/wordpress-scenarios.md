# esbuild WordPress Scenario

Node-free asset tooling for shared hosting, Playground, PHAR tools, and browser-backed PHP environments.

## Current Native Slice

Native JavaScript lexer for identifiers, esbuild-style numeric literal forms, strings, hashbangs, comments, and punctuators. A small module analyzer maps static imports, dynamic imports, exports, re-exports, import assertions/attributes, and package-vs-relative import classification. The fixture in `fixtures/wordpress-block-view.js` represents a block view script that can be preflighted on shared hosting without Node/npm and classified as importing `@wordpress/dom-ready` plus a relative `block.json` metadata import with `with { type: "json" }`.

## Next Task

Map additional parser/printer syntax such as `import.meta`/`new URL` asset references or TypeScript import/export forms.
