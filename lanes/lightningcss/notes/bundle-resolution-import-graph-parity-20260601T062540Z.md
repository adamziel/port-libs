# Bundle Resolution Import Graph Parity - 2026-06-01T062540Z

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T062540Z`

## Source Truth

- Upstream cache: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Native NAPI probes against `lightningcss.linux-x64-gnu.node` reject top-level function tokens in `@import` media tails with `Unexpected token Function("...")` before dependency reads:
  - `@import "b.css" foo(bar)` -> `Function("foo")`
  - `@import "b.css" supports(display: grid) l\61yer(theme.blocks)` -> `Function("layer")`
  - `@import "b.css" screen and/**/foo(bar)` -> `Function("foo")`

## Implementation

- `CssBundler::parseImportStatement()` now preserves the source offset of the final media tail.
- A top-level import-media token scan rejects function tokens before the existing media-query parser, while leaving nested functions inside parenthesized media features to the existing parser path.
- Diagnostics are raised as `CssBundleException('parser-error', 'Unexpected token Function("...")', ...)` at the media-tail token location, and reader-backed imports prove the child stylesheet is not read.
- `wordpress-bundle-import-graph.php --self-test` now includes a block stylesheet import whose malformed media-tail function is rejected before reading `blocks/card.css`.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 600 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` -> passed, including `malformed-import-media-function: rejected-before-read`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6448 assertions, 0 failures`.

## Status

- Focused PHP pass delta: full LightningCSS lane `6429 -> 6448` assertions (`+19`), `0` failures.
- Conservative mapped coverage remains `2359 / 3532`; this deepens the already mapped bundle/import graph grammar diagnostics bucket rather than claiming a new denominator unit.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `CssBundler` import parser and `MediaQueryParser` validation path.

## Non-Overlap

This does not touch resolver object-shape parity, CSS Modules selector pseudo canonicalization, custom at-rule visitors, source-map VLQ behavior, target prefixing, property-value minification, or CSSOM read/write coverage.
