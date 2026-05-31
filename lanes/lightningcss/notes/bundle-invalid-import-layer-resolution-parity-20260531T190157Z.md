# LightningCSS Bundle Invalid Import Layer Resolution Parity 2026-05-31T19:01Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T190157Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/parser.rs` parses `@import` layer modifiers with `LayerName::parse`, so a function form such as `layer(foo, bar)` or `layer()` is rejected while parsing the importing stylesheet.
  - `src/bundler.rs::load_file()` only resolves and reads import dependencies after stylesheet parsing succeeds.
  - `src/lib.rs::test_import` includes the invalid `@import 'test.css' layer(foo, bar) {};` layer-name parser boundary.

## Implementation

- `CssBundler::parseImportStatement()` now receives the source filename for parser diagnostics.
- Function-form `layer(...)` import modifiers are validated before dependency resolution:
  - empty `layer()` is rejected;
  - top-level comma-separated layer lists such as `layer(foo, bar)` are rejected;
  - escaped commas remain scanner-safe because the delimiter walk skips CSS escapes.
- Invalid layer modifiers now raise a `CssBundleException` parser diagnostic with the importing file location instead of reading the dependency and failing later in the minifier.
- `wordpress-bundle-import-graph.php` now smokes the WordPress-facing case where a block-theme import with an invalid layer list is rejected before `tokens.css` is read.

## Evidence

- Red-first probe before implementation:
  - `php -r '...'` for `@import "tokens.css" layer(foo, bar)` through `bundleWithReader()` failed late with `InvalidArgumentException: Invalid @layer block prelude: foo,bar`.
  - The reader had already read both `/entry.css` and `/tokens.css`, proving the invalid import modifier crossed into graph resolution.
- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 200 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `invalid-import-layer: rejected-before-read`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 3218 assertions, 0 failures`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative and filesystem reads, escaped import specifiers, escaped URL delimiters, CRLF escape consumption, repeated import condition merging, external import ordering, source-map import graph remapping, CSS Modules graph dependencies, custom-media import tails, media boolean simplification, and custom at-rule visitor bundling. It only closes the parser/graph boundary where invalid `@import layer(...)` names must be rejected before dependency resolution.

## Dependency Closure

No new support component is needed. This reuses the native PHP `CssBundler` scanner, CSS escape handling, source-location mapper, reader/resolver boundary, and existing WordPress bundle smoke. No Node, Rust, WASM, browser service, parser generator, or external package resolver is introduced.
