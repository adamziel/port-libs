# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01T00:24Z

## Source Truth

- Upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files: `src/bundler.rs`, `node/test/bundle.test.mjs`, and `tests/cli_integration_tests.rs`.
- Relevant upstream behavior:
  - `Bundler::new(...)` accepts source-map state while parser options also enable CSS Modules.
  - `Bundler::bundle()` collects import graph source text through `SourceProvider::load` while resolving ordinary `@import` and CSS Modules `composes ... from` dependencies.
  - The Node and CLI bundle paths expose code and source-map output together; CSS Modules paths also expose module exports.

## Native Delta

- Added `CssBundler::bundleCssModulesWithSourceMap()` for array-backed bundle calls that need `code`, `exports`, and `SourceMap` together.
- Added `CssBundler::bundleCssModulesWithReaderSourceMap()` for reader-backed source-provider calls with the same combined return shape.
- Added focused `CssBundlerTest.php` coverage for ordinary imports plus CSS Modules dependency imports in the same source-map graph, including resolver-returned reader paths.
- Extended `wordpress-bundle-import-graph.php` with a block CSS Modules smoke that verifies code, composed exports, and source-map source collection together.

## Evidence

- `php -l lanes/lightningcss/src/CssBundler.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 423 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - exits 0 and prints `css-modules-source-map: collected`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 5144 assertions, 0 failures`.

## Status Delta

- Focused `CssBundlerTest.php` assertions move `414 -> 423`.
- Full LightningCSS PHP lane assertions move `5135 -> 5144`.
- `lane-status.json` `phpPass` moves `5135 -> 5144`.
- `benchmarkDenominator.mapped` moves `2238 -> 2239 / 3532` for public CSS Modules bundle source-map/source-provider parity.

## Dependency Closure

No new support component is needed. This slice reuses the native `CssBundler`, `CssModulesTransformer`, `SourceMap`, resolver/reader callbacks, path normalizer, and `CssMinifier`; it does not add Node, Rust, WASM, browser, or external source-map runtime dependencies.

## Non-Overlap

- Avoided stale `port-lightningcss-current-rebase-20260525T053931Z-02383337.needs-lane-rework.md` custom-media import-tail rework.
- Did not duplicate accepted CSS Modules collision/recursive composes, file-backed CSS Modules, source-map raw VLQ/offsets, inline input maps, import condition diagnostics, media/layer/supports graph, target-prefixing, CSSOM, or custom at-rule behavior.
- Full upstream Rust, Node, and WASM runners were not executed.

## Follow-Up

Next bundle/import graph work can target accurate generated mapping offsets through the final minified bundle printer, external `.map` loading policy, or remaining resolver diagnostic parity.
