# Bundle Resolution Import Graph Parity - 2026-05-31T23:50Z

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T235032Z`
- Accepted base: `b2a0ea9050b31220cefa69c10914986b6a41bc76`
- Upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `src/parser.rs` parses `@import` with `expect_url_or_string()`, optional `layer` / `layer(...)`, optional `supports(...)`, and then `MediaList::parse(...)` while parsing the importing stylesheet.
- `src/bundler.rs` calls the parser in `load_file()` before it collects dependencies for resolver/source-provider traversal.
- Therefore malformed import condition tails are parser diagnostics on the importing file and do not read the referenced dependency first.

## Native Delta

- `CssBundler::parseImportStatement()` now validates the remaining `@import` media query list before dependency resolution or reader traversal.
- Invalid import media tails are reported as `CssBundleException` with kind `parser-error`, importing file, line, and column.
- Unbalanced `layer(...)` and `supports(...)` import modifiers now preserve importing-file diagnostics instead of surfacing locationless delimiter exceptions.
- `wordpress-bundle-import-graph.php --self-test` now proves malformed import media tails reject before reading block CSS.

## Red-First Evidence

- Before this patch, `@import "b.css" screen and;` read `/b.css` before failing with a raw `InvalidArgumentException`.
- Before this patch, unbalanced import `layer(` / `supports(` modifiers produced parser errors without source file, line, or column.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` - pass
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - pass, `1 test files, 405 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` - pass, `13 test files, 4995 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - pass, includes `malformed-import-media: rejected-before-read`
- `git diff --check -- lanes/lightningcss` - pass

## Status Delta

- Focused `CssBundlerTest.php`: 398 -> 405 assertions.
- Full LightningCSS lane evidence: 4965 -> 4995 assertions.
- Conservative mapped coverage remains `2212 / 3532` because this deepens the existing bundle/import graph parser cluster rather than adding a new denominator row.

## Non-Overlap

This slice avoids accepted escaped import specifier and at-keyword parsing, URL delimiter/trivia validation, malformed import source handling, invalid import layer-name validation, external import ordering, source provider diagnostics, CSS Modules import graphs, source maps, custom media, media range layer behavior, target prefixing, CSSOM, property/value, selector, and visitor/custom at-rule work. A stale May 25 custom-media rework note was inspected and is unrelated to this bundle/import condition validation slice.

## Dependency Closure

No new support component is needed. The slice reuses native `CssBundler`, `MediaQueryParser`, resolver/read callbacks, and existing import graph diagnostics. It does not require Node, Rust, WASM, a browser, package resolution, or a separate CSS parser.
