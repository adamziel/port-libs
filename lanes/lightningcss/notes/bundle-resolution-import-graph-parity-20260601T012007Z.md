# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01T01:20Z

## Source Truth

- Upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files: `src/bundler.rs` and `node/test/bundle.test.mjs`.
- Relevant upstream behavior:
  - `Bundler::load_file()` parses and resolves ordinary `CssRule::Import` dependencies before collecting CSS Modules `composes ... from` and dashed-ident dependency sources.
  - `Bundler::order()` still hoists CSS Modules dependency output before ordinary imported CSS in the final stylesheet.
  - `Bundler::bundle()` source-map source collection follows load order, while emitted CSS follows the ordered import graph.

## Native Delta

- Reordered `CssBundler::loadFile()` so ordinary `@import` dependencies are resolved/read before CSS Modules dependency traversal.
- Kept final item parsing after dashed-ident replacement, so emitted CSS remains unchanged while source-provider side effects and source-map source ordering match upstream.
- Added focused `CssBundlerTest.php` coverage for reader and resolver call order: entry, ordinary import, then CSS Modules dependency, with final output still hoisting CSS Modules CSS before the imported theme CSS.
- Updated existing CSS Modules resolver/source-map/file-backed import graph expectations to the upstream import-first load order.
- Extended `wordpress-bundle-import-graph.php` with a block CSS Modules smoke that checks read order, resolver order, output order, and export metadata together.

## Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 435 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 5329 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - exits 0 and prints `css-modules-import-first-resolution: ordered`.
- `php -l lanes/lightningcss/src/CssBundler.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - no syntax errors.
- JSON validation passed for `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json` and `lanes/lightningcss/lane-status.json`.
- `git diff --check -- lanes/lightningcss` - clean.

## Status Delta

- Focused `CssBundlerTest.php` assertions move `431 -> 435`.
- Full LightningCSS PHP assertions move `5325 -> 5329`.
- `lane-status.json` `phpPass` moves `5325 -> 5329`.
- Conservative mapped coverage moves `2288 -> 2289 / 3532` for upstream `Bundler::load_file` import-first resolution before CSS Modules dependency traversal.

## Dependency Closure

No new support component is needed. This slice reuses native `CssBundler`, `CssModulesTransformer`, `SourceMap`, reader/resolver callbacks, path handling, and the existing WordPress bundle example.

## Non-Overlap

- Avoided the stale May 25 `CustomMediaTransformer` rework note; current lane notes already contain later current-base custom-media import-tail coverage.
- Did not duplicate accepted CSS Modules collision/recursive composes, dashed env dependency traversal, file-backed graph behavior, source-map VLQ offsets, import condition diagnostics, media/layer/supports graph, target-prefixing, CSSOM, or custom at-rule behavior.
- Full upstream Rust, Node, and WASM runners were not executed.

## Follow-Up

Next bundle/import graph work can target remaining source-map generated mapping offsets through final bundle printing, external `.map` loading policy, or resolver diagnostics not already covered by import-first loading.
