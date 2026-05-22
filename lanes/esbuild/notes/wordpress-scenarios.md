# esbuild WordPress Scenario

Node-free asset tooling for shared hosting, Playground, PHAR tools, and browser-backed PHP environments.

## Current Native Slice

Native JavaScript lexer for identifiers, esbuild-style numeric literal forms, strings, hashbangs, comments, and punctuators. The fixture in `fixtures/wordpress-block-view.js` represents a block view script that can be preflighted on shared hosting without Node/npm.

## Next Task

Map upstream parser/printer tests for import/export syntax and add enough AST structure to distinguish WordPress package imports from relative asset imports.
