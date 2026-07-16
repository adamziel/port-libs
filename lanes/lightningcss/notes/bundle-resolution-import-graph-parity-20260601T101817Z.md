# LightningCSS Bundle Raw URL Import Graph Parity

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T101817Z`

Source truth:
- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Native addon probe of `bundleAsync()` showed `@import url(blocks/card(hero.css);` fails before dependency resolution with `Unexpected token BadUrl("blocks/card(hero.css")` at `/entry.css` line 1 column 8.
- The same probe showed `@import url(blocks/card[hero.css);` and `@import url(blocks/card{hero.css);` resolve with the raw `[` and `{` characters in the import specifier.
- `@import url(blocks/card)hero.css);` resolves the raw URL token at `blocks/card`, then rejects the following tail with `Unexpected token Delim(".")` at `/entry.css` line 1 column 29 before reading the dependency.

Implementation:
- `CssBundler` now scans raw `url(...)` imports with CSS URL-token close semantics instead of generic nested delimiter matching.
- Top-level statement scanning skips over raw `url(...)` token bodies so `[` and `{` in import sources do not split the statement or become bogus media tails.
- Import media tails now reject unexpected top-level delimiter tokens at upstream-style locations, covering the `url(blocks/card)hero.css)` tail case.
- Added WordPress import-graph smoke coverage for block stylesheet paths containing raw URL delimiters and for malformed raw URL import diagnostics that must reject before child reads.

Verification:
- `php -l lanes/lightningcss/src/CssBundler.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 692 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` -> passed, including `raw-url-import-delimiters: resolved`, `bad-raw-url-import-delimiter: rejected-before-read`, and `bad-raw-url-import-tail: rejected-before-resolution`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7380 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

Status delta:
- `CssBundlerTest.php` increased from 677 to 692 focused assertions.
- Full LightningCSS lane increased from 7365 to 7380 assertions.
- `lane-status.json` `phpPass` updated to `7380`; mapped upstream denominator remains unchanged because this deepens the existing bundle/import graph coverage cluster.

Dependency closure:
- No new support component is needed. The slice reuses the native PHP `CssBundler`, import scanner, resolver callback, source provider, and `CssBundleException` diagnostic path.
- The upstream native addon was used only as source-truth evidence; the PHP port still runs without Node/Rust/WASM at runtime.

Non-overlap:
- This is a bounded raw `url(...)` import tokenization and import graph diagnostic cluster.
- It does not repeat accepted comment-shaped BadUrl, literal null, surrogate escape, CRLF escape, escaped delimiter, quoted URL, supports/media/layer import parsing, external import ordering, CSS Modules, source map, media-query, CSSOM, custom at-rule, target-prefix, or property/value clusters.
- Root harness was not run; this isolated micro-slice used lane-focused verification only.
