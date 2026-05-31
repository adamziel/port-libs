# LightningCSS Bundle Parent-Relative Import Resolution 2026-05-31T17:20Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T171428Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `napi/src/lib.rs` default `JsSourceProvider::resolve()` and async provider resolve call `originating_file.with_file_name(specifier)` when no custom resolver is supplied.
  - `node/test/bundle.test.mjs` `only custom read`, where the reader consumes `path.normalize(file)` values from the default resolver path.
- Upstream behavior: relative filenames keep leading parent traversal segments. An entry `style.css` importing `../shared/tokens.css` resolves that reader/file key as `../shared/tokens.css`, not `shared/tokens.css`.

## Implementation

- `CssBundler::normalizePath()` now preserves leading `..` segments for relative paths while still collapsing ordinary `a/../b.css` segments and clamping absolute paths at the root.
- Added focused bundle assertions for eager in-memory files and reader-backed SourceProvider paths.
- Extended `wordpress-bundle-import-graph.php` with a shared preset import outside the current theme stylesheet directory.

## Verification

- Baseline focused before this slice: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 133 assertions, 0 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 136 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2577 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` exits 0 and prints `parent-relative-import: resolved`.
- PHP lint passed for `CssBundler.php`, `CssBundlerTest.php`, and `wordpress-bundle-import-graph.php`.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness: not run - isolated micro-slice.

## Coverage Delta

- PHP pass evidence: `2574` -> `2577`.
- Conservative mapped coverage: `1562 / 3532` -> `1563 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the existing native `CssBundler`, SourceProvider reader boundary, resolver callback boundary, path normalizer, and file map. No Node, Rust, browser service, parser generator, filesystem crawler, or package resolver is introduced.

## Non-Overlap

This slice avoids accepted semicolonless EOF import diagnostics, post-import `@layer` barriers, resolver result shape diagnostics, file-backed SourceProvider reads, escaped import token decoding, URL import modifiers, external import ordering, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, CSS Modules dependency graphs, source-map offset behavior, CSSOM, target-prefixing, media-query, and custom at-rule visitor slices. The stale 2026-05-25 CustomMedia rework note predates the accepted CustomMedia scanner/import-tail behavior and is unrelated to this bundle path-normalization fix.
