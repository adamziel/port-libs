# Bundle Malformed Import Source Parity - 2026-05-31T21:11Z

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T211121Z`
- Accepted base: `3a3374ad59c06e8a3561833481036dd945373160`
- Upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `src/parser.rs` parses `@import` source tokens with `expect_url_or_string()` before resolving bundle dependencies.
- `src/bundler.rs` parses each stylesheet before collecting and resolving dependencies.
- `src/error.rs` maps malformed tokens to parser errors with filename and location.
- `node/test/bundle.test.mjs` covers resolver/read and syntax-error diagnostics; the upstream Node runner was not executed locally because the cached checkout is missing Node dependency `detect-libc`.

## Native Delta

- `CssBundler::parseImportStatement()` now rejects malformed direct string and `url(...)` import sources before resolver or reader traversal.
- Import-source quoted string scanning rejects unterminated strings and raw newline/form-feed characters while preserving CSS escaped-newline and escaped CRLF continuation behavior.
- Malformed import-source diagnostics use the importing file location and message `Invalid @import source`.
- `wordpress-bundle-import-graph.php --self-test` now includes a reader-backed malformed quoted import source and verifies only the entry stylesheet is read.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` - pass
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - pass, `1 test files, 354 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` - pass, `13 test files, 4415 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - pass, includes `malformed-import-source: rejected-before-read`
- `git diff --check -- lanes/lightningcss` - pass

## Status Delta

- Focused `CssBundlerTest.php`: 354 assertions.
- Full LightningCSS lane evidence: 4390 -> 4415 assertions.
- Conservative mapped coverage remains `2117 / 3532` because this deepens the already represented bundle/import-source parser cluster rather than adding a new denominator row.

## Non-Overlap

This slice avoids accepted resolver callback ordering, default relative resolution, filesystem source-provider reads, escaped delimiter and CRLF import paths, quoted `url(...)` trailing-token source parsing, layer-name validation, supports/media/layer wrapping and merging, external import ordering, CSS Modules graphs, source maps, custom media, CSSOM, target prefixing, property/value, media range, and visitor/custom at-rule work.

## Dependency Closure

No new support component is needed. The slice reuses native `CssBundler`, bounded CSS string/escape scanners, resolver/read callbacks, `CssMinifier`, and the existing import graph model. It does not require Node, Rust, WASM, a browser, package resolution, or a separate CSS parser.
