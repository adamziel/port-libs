# LightningCSS Bundle Resolution Import Graph Parity 2026-05-31T18:35Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T183551Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/parser.rs` parses import sources through `cssparser::Parser::expect_url_or_string()`, so CSS escaped code points are decoded before `ImportRule.url` reaches bundle resolution.
  - CSS tokenization treats a CRLF pair as a single normalized newline. After a hex escape, that normalized newline is the optional whitespace terminator and is not part of the decoded URL/string token.
  - `src/bundler.rs::load_file()` passes the decoded import URL to `SourceProvider::resolve()` and then reads the resolved file through the import graph.

## Native Delta

- `CssBundler::decodeCssEscapes()` now consumes a full CRLF pair after a CSS hex escape before resolving `@import` string and `url()` sources.
- `CustomMediaTransformer::decodeCssEscapes()` now applies the same CRLF terminator behavior while rewriting `@import` media tails that reference custom media aliases.
- `wordpress-bundle-import-graph.php` now smokes CRLF-terminated hex escaped block-theme import paths.

## Evidence

- Red check before fix: an ad hoc `CssBundler` run for `@import "blocks/card\2e\r\ncss"` failed with `CssBundleException: Could not read /blocks/card.\ncss`.
- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors.
- `php -l lanes/lightningcss/src/CustomMediaTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CustomMediaTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 173 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomMediaTransformerTest.php` => `1 test files, 40 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 3063 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => exits 0 and prints `escaped-crlf-imports: resolved`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `3060` to `3063` assertions.
- Conservative mapped coverage remains `1684 / 3532`; this deepens the already represented bundle/import graph and custom-media scanner clusters rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses native PHP scanner/parser helpers in `CssBundler`, `CustomMediaTransformer`, the existing minifier output path, the resolver callback API, and the existing WordPress bundle smoke. No Node, Rust, WASM, browser service, package resolver, or external credentialed provider is introduced.

## Non-Overlap

This slice avoids accepted escaped delimiter import handling, escaped string specifier decoding, parent-relative imports, file-backed CSS Modules graph resolution, duplicate supports/media/layer merging, import modifier ordering, external import ordering diagnostics, source-map source collection/remapping, custom at-rule visitor work, CSSOM declaration work, target prefixing, property-value minifier clusters, and media-query range handling. It only fixes CRLF whitespace terminator consumption after CSS hex escapes in import source decoding and the directly coupled custom-media import-tail scanner.
