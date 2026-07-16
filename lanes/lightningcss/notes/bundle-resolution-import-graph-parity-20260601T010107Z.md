# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01T01:01Z

## Source Truth

- Upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files: `src/parser.rs`, `src/rules/import.rs`, `src/rules/supports.rs`, and `src/bundler.rs`.
- `TopLevelRuleParser` parses `@import` source, optional `layer`, optional `supports()`, then media before the bundler resolves dependencies.
- `supports()` uses `SupportsCondition::parse` or declaration parsing inside the import prelude, so malformed conditions fail while parsing the importing stylesheet.
- `Bundler::load_file()` resolves and reads dependencies only after stylesheet parsing succeeds.

## Native Delta

- `CssBundler::parseImportStatement()` now validates import `supports()` conditions before calling the resolver or source provider.
- Empty supports conditions, dangling logical operators, mixed top-level `and`/`or` without grouping, and `not` without a parenthesized/function operand now raise a parser error at the import rule location.
- Valid declaration conditions, `selector(...)`, and unknown single-token/function conditions continue to bundle.
- The WordPress bundle smoke verifies an invalid supports-gated block-style import reads only the entry stylesheet.

## Evidence

- Baseline focused command before this slice: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 431 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 457 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` => exits 0 and prints `malformed-import-supports: rejected-before-read`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 5273 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` => no output, exits 0.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused `CssBundlerTest.php`: `431 -> 457` assertions.
- Full LightningCSS lane: `5247 -> 5273` assertions.
- Conservative mapped coverage: `2248 -> 2249 / 3532`.

## Dependency Closure

No new support component is needed. This reuses `CssBundler` scanners, source-location mapping, the resolver/source-provider boundary, `CssMinifier`, and the existing WordPress bundle smoke.

## Non-Overlap

Avoided the stale May 25 `CustomMediaTransformer` rework note and accepted custom-media import-tail work. This does not repeat malformed media-tail, import source, layer-name, external import, URL/escaped specifier, CSS Modules, source-map, CSSOM, media-query, target-prefixing, or custom at-rule clusters. The slice is limited to import `supports()` condition validation before graph resolution.
