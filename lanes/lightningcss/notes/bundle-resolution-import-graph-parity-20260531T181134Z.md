# LightningCSS Bundle Resolution Import Graph Parity 2026-05-31T18:11Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T181134Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/parser.rs` parses `@import` sources with `cssparser::Parser::expect_url_or_string()`, so escaped URL-token delimiters remain part of the decoded URL token.
  - `src/rules/import.rs` stores the decoded URL on `ImportRule`.
  - `src/bundler.rs::load_file()` passes that decoded import URL into `SourceProvider::resolve()` and then preserves the resolved file in the import graph.
  - The old lane rework note for `CustomMediaTransformer` import media-tail scanning is relevant to this delimiter scanner path; this slice resolves the escaped-delimiter part without replaying stale status/manifest edits.

## Native Delta

- `CssBundler` now skips CSS escapes while matching top-level delimiters and `url(...)` parentheses, so `@import url(./icon\).css)` resolves `./icon).css` instead of truncating at `/icon`.
- Resolver callbacks receive the decoded specifier for escaped delimiter paths, preserving upstream-like import graph origin reporting.
- `CustomMediaTransformer` uses the same escaped-delimiter scanner while finding `@import` media tails, and normalizes rewritten `url(...)` sources to quoted import strings before minification.
- The WordPress bundle example now asserts escaped `url()` delimiter imports for block-theme asset paths.

## Evidence

- Red check before fix: an ad hoc `CssBundler` run for `@import url(./icons\).css)` failed with `CssBundleException: Could not read /icons`.
- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors.
- `php -l lanes/lightningcss/src/CustomMediaTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CustomMediaTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 164 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomMediaTransformerTest.php` => `1 test files, 39 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2885 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => exits 0 and prints `escaped-url-delimiters: resolved`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2881` to `2885` assertions.
- Conservative mapped coverage remains `1637 / 3532`; this deepens the already mapped bundle/import-graph and custom-media scanner clusters rather than adding a new counted upstream helper row.

## Dependency Closure

No new support component is needed. This reuses native PHP scanner/parser helpers in `CssBundler`, `CustomMediaTransformer`, `CssMinifier`, the existing resolver callback API, and the existing WordPress bundle smoke. No Node, Rust, WASM, browser service, package resolver, or external credentialed provider is introduced.

## Non-Overlap

This slice avoids accepted source-map source collection, file-backed CSS Modules graph resolution, parent-relative imports, escaped string specifier decoding, import modifier ordering, duplicate supports/media/layer merging, external import ordering diagnostics, media-type boolean conjunction, CSS Modules dependency imports, CSSOM declaration work, target prefixing, property-value minifier clusters, and custom at-rule visitor clusters. It only adds escaped delimiter handling inside URL-token import source scanning and the directly coupled custom-media import-tail scanner.
