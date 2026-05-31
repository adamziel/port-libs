# LightningCSS Bundle Layer Import Prelude Barrier 2026-05-31T16:55Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T165506Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream read: `src/lib.rs::test_import` includes `@layer foo; @import url(foo.css); @layer bar; @import url(bar.css)` as an `UnexpectedImportRule` case.
- Bundler source context: `src/bundler.rs::inline()` only preserves `@layer` statements while scanning the import prelude. A layer-order statement before imports stays valid, but once an import has appeared, a later `@layer` statement is a barrier for subsequent imports.

## Native Delta

- `CssBundler::topLevelItems()` now tracks whether an import has been seen.
- Top-level `@layer` statements before imports remain transparent to import ordering.
- A top-level `@layer` statement after an import can still serialize when no later imports follow, but it closes the import prelude so a later `@import` raises the existing upstream-style parser diagnostic with file, line, and column.
- `wordpress-bundle-import-graph.php` now keeps the valid layer-order statement before imports and smokes a broken block-theme graph that places `@layer theme.blocks;` between imports.

## Evidence

- Red-first focused run after adding the assertion:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` =>
  `1 test files, 111 assertions, 1 failures`, failing because the second import was bundled instead of rejected.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` =>
  `1 test files, 116 assertions, 0 failures`.
- Full LightningCSS lane:
  `php tools/run-tests.php lanes/lightningcss/tests` =>
  `13 test files, 2327 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` =>
  exits 0 and prints `post-import-layer: rejected`.
- PHP lint:
  `php -l lanes/lightningcss/src/CssBundler.php && php -l lanes/lightningcss/tests/CssBundlerTest.php && php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` =>
  no syntax errors.
- Metadata JSON decode:
  `php -r 'foreach (["lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json", "lanes/lightningcss/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, " ok\n"; }'` =>
  both files decoded successfully.
- Whitespace:
  `git diff --check -- lanes/lightningcss` => exits 0.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2321` to `2327 pass / 0 fail`.
- Conservative mapped coverage: `1450 / 3532` to `1451 / 3532`.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, import prelude style/namespace diagnostics, URL import modifier parsing, external import ordering, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, license-comment preservation, malformed resolver-result diagnostics, reader-backed SourceProvider behavior, CSS Modules dependency graphs, source-map offsets, CSSOM work, target prefixing, media-range, and custom at-rule visitor slices. It only maps the remaining import-prelude barrier where an intervening top-level `@layer` statement after an import prevents later imports.

## Dependency Closure

No new support component is needed. This reuses the native `CssBundler` top-level scanner, source-location helper, path resolver, `CssMinifier`, and existing `CssBundleException` model. No Node, Rust, browser service, parser generator, filesystem crawler, or external resolver package is introduced.
