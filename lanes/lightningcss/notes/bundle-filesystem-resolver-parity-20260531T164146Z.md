# LightningCSS Bundle Filesystem Resolver Parity 2026-05-31T16:41Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T164146Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `node/test/bundle.test.mjs` `only custom resolve` reads real fixture files while a resolver callback rewrites `root:` specifiers.
  - `src/bundler.rs::load_file()` reads resolved paths through the active `SourceProvider` and then feeds them into the same dependency ordering and inlining phases as in-memory sources.

## Native Delta

- Added `CssBundler::bundleFile()` for file-backed bundles that use the same resolver/import graph, ordering, cycle suppression, custom media sharing, and minification path as `bundle()` and `bundleWithReader()`.
- Filesystem read failures are wrapped as `CssBundleException` kind `resolver-error`, with no location for entry reads and import-rule location for dependency reads, matching the existing SourceProvider boundary.
- `wordpress-bundle-import-graph.php` now smokes a temporary block-theme stylesheet tree where a custom resolver maps `pkg:` specifiers to a vendor token file while ordinary theme imports read from disk.

## Evidence

- Baseline focused before this slice: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 110 assertions, 0 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 112 assertions, 0 failures`.
- Full LightningCSS lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2315 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `filesystem-provider: resolved`.
- PHP lint passed for:
  - `lanes/lightningcss/src/CssBundler.php`
  - `lanes/lightningcss/tests/CssBundlerTest.php`
  - `lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- Whitespace: `git diff --check -- lanes/lightningcss` => exits 0.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2313` to `2315` assertions.
- Conservative mapped coverage: `1446 / 3532` to `1447 / 3532`.
- Counted upstream behavior: Node `bundle.test.mjs` `only custom resolve`, where a resolver callback rewrites specifiers while the default source provider reads resolved files and preserves recursive dependency-before-importer order.

## Dependency Closure

No new support component is needed. This reuses PHP's built-in filesystem read primitive inside the existing native `CssBundler`, resolver callback boundary, path normalizer, `CssMinifier`, `CustomMediaTransformer`, and bundle exception model. No Node, Rust, browser service, parser generator, package resolver, or filesystem crawler is introduced.

## Non-Overlap

This slice avoids accepted reader-backed SourceProvider callbacks, malformed resolver-result diagnostics, default in-memory relative resolution, URL import modifiers, external import ordering, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, CSS Modules dependency graphs, source-map offsets, CSSOM shorthand work, target prefixing, media-range, and custom at-rule visitor slices. It only adds the upstream file-backed SourceProvider path for custom resolver bundles.
