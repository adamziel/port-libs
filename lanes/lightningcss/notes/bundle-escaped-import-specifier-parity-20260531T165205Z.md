# LightningCSS Bundle Escaped Import Specifier Parity 2026-05-31T16:52Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T165205Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/parser.rs` parses `@import` sources with `input.expect_url_or_string()?`, so CSS string and `url(...)` escapes are decoded before the value reaches `ImportRule.url`.
  - `src/bundler.rs::load_file()` resolves `ImportRule.url` through the `SourceProvider`, using the decoded URL/specifier for default file resolution, custom resolver callbacks, external imports, and imported source locations.
  - `src/rules/import.rs::ToCss` serializes the decoded import URL as a CSS string.

## Native Delta

- `CssBundler::parseImportStatement()` now decodes CSS escapes for quoted import strings and unquoted `url(...)` import sources before calling the resolver/default path handler.
- The decoder handles simple escaped characters, hex escapes with optional whitespace terminators, line continuations, and invalid codepoint replacement.
- Added focused coverage for escaped spaces and slashes under default relative resolution, escaped resolver callback specifiers, and escaped external URL schemes.
- `wordpress-bundle-import-graph.php` now includes an escaped block stylesheet import path to model migrated theme CSS with escaped filenames.

## Evidence

- Baseline focused gate before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` =>
  `1 test files, 110 assertions, 0 failures`.
- Red-first spot checks before implementation:
  escaped quoted path `./theme\000020components.css` resolved as `/theme/000020components.css`;
  escaped `url(./icons\2f arrow.css)` resolved as `/icons/2f arrow.css`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` =>
  `1 test files, 114 assertions, 0 failures`.
- Full LightningCSS lane:
  `php tools/run-tests.php lanes/lightningcss/tests` =>
  `13 test files, 2317 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/lightningcss/src/CssBundler.php`,
  `php -l lanes/lightningcss/tests/CssBundlerTest.php`, and
  `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` passed.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` exits 0 and prints the escaped `.wp-block-cover` stylesheet plus existing bundle diagnostics.
- Metadata JSON decode passed for `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json`.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2313` to `2317` assertions.
- Conservative mapped coverage: `1446 / 3532` to `1449 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the native `CssBundler`, resolver/read boundary, path normalizer, `CssMinifier`, and existing bundle exception model. No Node, Rust, browser service, parser generator, filesystem crawler, or external resolver package is introduced.

## Non-Overlap

This slice avoids accepted SourceProvider reads, malformed resolver-result shape diagnostics, default relative path ordering, external import ordering, url() import modifier parsing, media/layer/supports wrapping, repeated import last-position behavior, custom-media sharing, CSS Modules escaped dependency specifiers, CSS Modules import graphs, source-map behavior, CSSOM work, target prefixing, media-range handling, and custom at-rule visitor slices. It only covers upstream @import source token escape decoding before bundle graph resolution.
