# LightningCSS Bundle CSS Modules File Resolution Import Graph Parity 2026-05-31T17:42Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T174259Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/bundler.rs::load_file()` reads every resolved path through the active `SourceProvider` before collecting ordinary `@import` and CSS Modules dependencies.
  - `src/bundler.rs::test_css_module` verifies bundled CSS Modules imports, `composes ... from` dependency resolution, and dependency export flattening.
  - `napi/src/lib.rs` `JsSourceProvider::read()` / `resolve()` and `node/test/bundle.test.mjs` `only custom resolve` show file-backed reads plus custom resolver callbacks feeding the same bundle graph.

## Native Delta

- Added `CssBundler::bundleCssModulesFile()` so filesystem-backed bundles can enable CSS Modules with the same resolver/import graph used by `bundleFile()`, `bundleCssModules()`, and `bundleCssModulesWithReader()`.
- The focused test covers file-backed CSS Modules graph resolution where:
  - `composes: ... from "pkg:..."` resolves through the custom resolver and reads from disk;
  - a normal relative `@import` in the same entry resolves through the same callback;
  - CSS Modules dependencies are emitted before ordinary imports;
  - ordinary imports keep their layer wrapper;
  - entry exports include the composed dependency class.
- `wordpress-bundle-import-graph.php` now smokes file-backed CSS Modules bundling for block CSS without Node/WASM.

## Evidence

- Baseline focused before this slice: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 147 assertions, 0 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 150 assertions, 0 failures`.
- Full LightningCSS lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2797 assertions, 0 failures`.
- PHP lint passed for:
  - `lanes/lightningcss/src/CssBundler.php`
  - `lanes/lightningcss/tests/CssBundlerTest.php`
  - `lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- Example smoke: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `css-modules-file: resolved`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused `CssBundlerTest.php`: `147` to `150` assertions.
- Full LightningCSS PHP evidence: `2794` to `2797` assertions.
- Conservative mapped coverage: `1601 / 3532` to `1602 / 3532`.

## Dependency Closure

No new support component is needed. This reuses PHP filesystem reads inside the existing native `CssBundler`, resolver callback boundary, path normalizer, `CssModulesTransformer`, `CssMinifier`, and bundle exception model. No Node, Rust, browser service, parser generator, external resolver package, or filesystem crawler is introduced.

## Non-Overlap

This slice avoids accepted plain file-backed resolver behavior, reader-backed SourceProvider callbacks, escaped import specifier decoding, resolver-result shape diagnostics, semicolonless EOF imports, URL import modifiers, import-prelude barriers, external import ordering, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, in-memory CSS Modules dependency graphs, CSS Modules project-root/content-hash behavior, SourceMap offsets, CSSOM work, target-prefixing, media-query, property-value/color/grid/font, and custom at-rule visitor slices. It only adds the upstream-backed filesystem SourceProvider plus CSS Modules bundle graph combination.
