# LightningCSS Bundle Source Provider Read Parity 2026-05-31T15:55Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T155518Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `node/test/bundle.test.mjs` `only custom read`, `only custom resolve`, `read throw`, `read throw with location info`, and `read return non-string`.
  - `napi/src/lib.rs` `JsSourceProvider::read()` and `JsSourceProvider::resolve()` show the source-provider boundary: resolved paths are passed to read callbacks, read errors are wrapped by the bundler, entry read errors carry no location, imported read errors carry the originating import location, and non-string reads fail with an `expect String` diagnostic.
  - `src/bundler.rs::load_file()` wraps source-provider read errors with the import rule location only when the failing file came from an import rule.

## Native Delta

- Added `CssBundler::bundleWithReader()` and `CssBundler::bundleCssModulesWithReader()` as native PHP SourceProvider-style APIs for lazy read callbacks.
- Reader-backed bundles reuse the existing resolver/import graph: default relative resolution, custom resolver rewrites, cycle handling, import ordering, CSS Modules, custom media, and minification all flow through the same `loadFile()` path.
- Reader callback exceptions now become `CssBundleException` kind `resolver-error`, with no source location for the entry read and the importing file/line/column for imported reads.
- Non-string reader results are rejected with the upstream-aligned `expect String, got: Number` diagnostic.
- `wordpress-bundle-import-graph.php` now smokes a block-theme bundle loaded through a reader callback plus a `pkg:` resolver rewrite, without Node or filesystem scanning.

## Evidence

- Red-first focused run after adding tests, before implementation:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` =>
  `1 test files, 88 assertions, 2 failures`
  (`Call to undefined method PortLibs\LightningCSS\CssBundler::bundleWithReader()`).
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` =>
  `1 test files, 105 assertions, 0 failures`.
- Full LightningCSS lane:
  `php tools/run-tests.php lanes/lightningcss/tests` =>
  `13 test files, 2042 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` =>
  exits 0 and prints `reader-provider: resolved`.
- PHP lint:
  `php -l lanes/lightningcss/src/CssBundler.php`,
  `php -l lanes/lightningcss/tests/CssBundlerTest.php`, and
  `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` =>
  no syntax errors.
- Metadata JSON decode:
  `php -r 'foreach (["lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json", "lanes/lightningcss/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, " ok\n"; }'` =>
  both files decoded successfully.
- Whitespace:
  `git diff --check -- lanes/lightningcss` => exits 0.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2025` to `2042` assertions.
- Conservative mapped coverage: `1340 / 3532` to `1345 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the native `CssBundler`, resolver callback boundary, path normalizer, `CssMinifier`, `CustomMediaTransformer`, and existing bundle exception model. No Node, Rust, browser service, parser generator, filesystem crawler, or external resolver package is introduced.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default eager-map relative resolution, import-prelude diagnostics, external import ordering, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, license-comment preservation, malformed resolver-result shape diagnostics, CSS Modules pure-mode selector work, alpha-color fallback, outline CSSOM, FunctionExit visitor chaining, source-map offsets, CSSOM shorthand work, target prefixing, media-range, and custom at-rule visitor slices.
