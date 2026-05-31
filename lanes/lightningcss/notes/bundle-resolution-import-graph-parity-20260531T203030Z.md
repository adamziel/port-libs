# LightningCSS Bundle Resolution Import Graph Parity 2026-05-31T20:30Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T203030Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/bundler.rs::load_file()` combines parent and child `@import` layers as parsed `LayerName` segments, extending the parent segment vector with child segments instead of concatenating raw CSS text.
  - `src/bundler.rs::test_bundle` covers named, anonymous, nested, repeated, and unsupported layer import graph behavior.
  - `src/lib.rs::test_import_layer` verifies escaped layer-name serialization such as `layer(foo\20 bar)` becoming `layer(foo\ bar)` and rejects literal comma-separated layer names in import modifiers.

## Native Delta

- `CssMinifier` now parses layer-name lists and dotted layer segments while respecting CSS escapes, so escaped dots and commas remain inside identifier segments rather than becoming hierarchy or list separators.
- Layer identifiers are decoded and reserialized with unambiguous CSS escapes before final bundle minification. This preserves `layer(foo\2e bar)` as `foo\.bar`, `layer(foo\20 bar)` as `foo\ bar`, and `layer(foo\2c bar)` as `foo\,bar`.
- Bundled parent/child import layers now compose escaped segment names correctly, e.g. `layer(foo\2e bar)` plus nested `layer(baz\20 qux)` serializes as `@layer foo\.bar.baz\ qux`.
- `wordpress-bundle-import-graph.php` now smokes escaped plugin/palette layer names in a block-theme import graph.

## Evidence

- Red probe before fix: an ad hoc bundle for `@import "b.css" layer(foo\2e bar)` emitted `@layer foo\2ebar{...}`, which changes the CSS hex escape.
- `php -l lanes/lightningcss/src/CssMinifier.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 278 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` => `1 test files, 1462 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 4156 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `escaped-layer-imports: preserved`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moves from `4150` to `4156` assertions.
- Conservative mapped coverage remains `2078 / 3532`; this deepens the already represented bundler import-layer graph and upstream `src/lib.rs::test_import_layer` escaped layer-name cluster instead of claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses native PHP `CssBundler`, `CssMinifier`, CSS escape decoding, import graph ordering, and the existing WordPress bundle smoke. No Node, Rust, WASM, browser service, package resolver, external parser, or credentialed provider is introduced.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, filesystem source-provider reads, escaped import source paths, escaped URL delimiters, CRLF hex escapes in import sources, import modifier ordering, duplicate supports/media/layer merging, external import ordering diagnostics, custom-media sharing, CSS Modules dependency graphs, source-map remapping, CSSOM declaration work, target prefixing, property-value minifier clusters, media-query range handling, and custom at-rule visitor slices. It only covers escaped layer-name identifiers as they pass through bundled `@import` layer graph composition and final layer serialization.
