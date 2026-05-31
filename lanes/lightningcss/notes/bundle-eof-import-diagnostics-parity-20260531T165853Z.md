# LightningCSS Bundle EOF Import Diagnostics 2026-05-31T16:58Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T165853Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `node/test/bundle.test.mjs` `read throw with location info` returns resolver-provided source ending in `@import "bar.css"` with no semicolon, and LightningCSS still resolves `bar.css` before reporting read failure at `foo.css` line 1 column 1.
  - `node/test/bundle.test.mjs` `should throw with location info on syntax errors` reports `Unexpected end of input` for resolver-provided `.foo` with filename `tests/testdata/foo.css` and column 5.

## Native Delta

- `CssBundler` now treats a top-level trailing `@import` at EOF as an import rule, matching the SourceProvider path that accepts semicolonless final imports.
- Other non-empty trailing top-level input without a block now throws `CssBundleException` kind `parser-error` with message `Unexpected end of input` and the source filename/line/column.
- `wordpress-bundle-import-graph.php` now smokes a reader-backed block-theme import graph whose entry file is only a semicolonless final `@import`.

## Evidence

- Baseline spot checks before the patch:
  - `bundleWithReader("foo.css", read => @import "bar.css")` printed raw `@import "bar.css"` instead of resolving `bar.css`.
  - `bundleWithReader("foo.css", read => .foo)` printed raw `.foo` instead of reporting a parser diagnostic.
- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => 1 test file, 119 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests` => 13 test files, 2368 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `reader-eof-import: resolved`.
- `git diff --check -- lanes/lightningcss` => exits 0.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2361` to `2368` assertions.
- Conservative mapped coverage: `1458 / 3532` to `1460 / 3532`.
- Counted upstream behavior: 2 Node `bundle.test.mjs` checks for semicolonless EOF import resolution and parser-error source locations from resolver-provided CSS.

## Dependency Closure

No new support component is needed. This reuses the native `CssBundler` top-level scanner, existing SourceProvider/read callback boundary, path resolver, minifier, and bundle exception model. No Node, Rust, browser service, parser generator, external package resolver, or filesystem crawler is introduced.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, resolver result shape diagnostics, file-backed SourceProvider reads, URL import modifiers, import prelude ordering diagnostics, external import ordering, media/layer/supports wrapping and merging, repeated import last-position behavior, custom-media sharing, CSS Modules dependency graphs, source-map offsets, CSSOM shorthand work, target prefixing, media-range, SourceMap overflow, and custom at-rule visitor slices.

The visible stale 2026-05-25 `CustomMediaTransformer` import-tail rework note predates later accepted custom-media scanner integrations and is not part of this bundle EOF import diagnostic path.
