# LightningCSS Bundle SourceMap Import Graph Parity 2026-05-31T17:49Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T174905Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/bundler.rs::load_file()` adds each loaded file to the provided source map and stores source contents when no input source map is present.
  - `src/bundler.rs` `SourceProvider` paths share the same resolved filename flow as array-backed bundle inputs.
  - `node/test/bundle.test.mjs` exercises resolver-backed and reader-backed import graphs with recursive dependency-before-importer emission.

## Native Delta

- `CssBundler::bundleWithSourceMap()` now returns bundled CSS plus a `SourceMap` that lists the resolved entry/import files and their source contents.
- `CssBundler::bundleWithReaderSourceMap()` applies the same source-map collection to reader-backed source providers.
- Source-map collection records only loaded local sources. External URL imports remain serialized at the top of the bundle and are not added as local source files.
- The block-theme bundle example now asserts that source-map source collection follows the WordPress-style import graph, including nested imports and escaped specifier decoding.

## Evidence

- Baseline before the patch: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 147 assertions, 0 failures`; no bundle-level source-map collection assertions existed.
- Focused after fix: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 154 assertions, 0 failures`.
- Full LightningCSS lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2801 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `source-map-sources: collected`.
- PHP lint: `php -l lanes/lightningcss/src/CssBundler.php`, `php -l lanes/lightningcss/tests/CssBundlerTest.php`, and `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` passed.
- Diff hygiene: `git diff --check -- lanes/lightningcss` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2794` to `2801` assertions.
- Conservative mapped coverage: `1601 / 3532` to `1603 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the native `CssBundler`, `SourceMap`, resolver/reader source-provider path, path normalizer, import scanner, and `CssMinifier`. No Node, Rust, WASM, browser service, package resolver, or external filesystem crawler is introduced.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, filesystem source-provider reads, escaped specifier decoding, resolver-result shape diagnostics, EOF import handling, import-prelude barriers, external import ordering errors, import modifier order parsing, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, CSS Modules dependency graphs, source-map VLQ/offset/import mechanics, CSSOM work, target prefixing, media-range validation, and custom at-rule visitor slices. The stale 2026-05-25 `CustomMediaTransformer` rework note is unrelated to this bundle source-map path and is not touched here.
