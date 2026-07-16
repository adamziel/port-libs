# LightningCSS Media Query Compact Negation Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T111138Z`

Base: `87b9b5e4231e455752546908281e85ed6f228913`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source:
  - `src/lib.rs::test_media`, which covers media-query parser normalization and diagnostics.
  - `src/media_query.rs`, where `not` is parsed as an identifier modifier, not as a condition function token.
- Local upstream native oracle from `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` confirmed:
  - `@media not(color) { ... }` rejects with `Unexpected token Function("not")`.
  - `@media n\6f t(color) { ... }` rejects with the same diagnostic after escape decoding.
  - `@media not (color) { ... }` and `@media not/*x*/(color) { ... }` serialize as `@media not (color){...}`.

## Native Delta

- `MediaQueryParser` now distinguishes externally authored compact `not(...)` condition functions from internally compacted valid `not (condition)` forms.
- `CssMinifier` validates authored `@media` preludes before comment/whitespace stripping, so layered `@media not(color)` is rejected instead of being normalized into a valid negation.
- Recovery warnings now decode escaped function identifiers, so `n\6f t(color)` reports `Unexpected token Function("not")` and drops the invalid media block.
- The WordPress media range/layer recovery example now covers escaped compact `not(color)` removal while preserving later valid media rules.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/src/MediaQueryParser.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-recovery.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/CssMinifierTest.php`
  - Result: `2 test files, 2511 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-recovery.php --self-test`
  - Result: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 7505 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `7496 -> 7505 pass / 0 fail`.
- Conservative mapped coverage remains unchanged; this deepens existing media-query range/layer parser and recovery coverage rather than claiming a new denominator row.

## Non-Overlap

- Does not repeat accepted x-resolution serialization/prefixing, escaped media feature identifiers, bare-not operand validation, value-function condition guards, invalid range layer validation, target-prefix media range lowering, bundle/import graph media propagation, SourceMap, CSS Modules, CSSOM, custom at-rule, property-value, or selector-prefix clusters.

## Dependency Closure

No new support component is needed. The slice reuses native PHP media-query parsing/minification, focused lane tests, the existing WordPress media range layer recovery example, and a local pinned upstream native oracle only for source-truth confirmation.

## Next Task

Continue with non-overlapping LightningCSS media-query parser recovery/serialization, target-prefix browser-boundary cases, CSSOM, CSS Modules, SourceMap, bundler, property-value/font/grid/color, or custom-at-rule parity.
