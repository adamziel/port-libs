# Media Query Unitless Length Range Layer Parity - 2026-06-01

Slice: `lightningcss-media-query-range-layer-parity-20260601T063800Z`

## Source Truth

- Pinned upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Relevant upstream behavior was confirmed with the native binding:
  - `@media (width >= 2)` serializes as `@media (width>=2px)`.
  - `@media (min-width: 2)` serializes as `@media (width>=2px)`.
  - `@media (2 <= width <= 4)` serializes as `@media (2px<=width<=4px)`.
  - Firefox 60 target fallback lowers `(width >= 2)` to `(min-width:2px)`.
  - Zero remains unitless: `(width >= 0)` serializes as `(width>=0)`.

## Implementation

- `MediaQueryParser` now minifies media range values with feature-type context.
- Length media features accept unitless numeric values and serialize nonzero values with `px`, matching upstream length parsing.
- The type-aware normalization is applied to:
  - modern name-first ranges;
  - value-first ranges and intervals;
  - legacy `min-` / `max-` feature aliases;
  - plain length media features such as `(width: 2)`;
  - negated simple range inversion and target fallback lowering.
- Unknown/custom range features remain unchanged, so `(theme-breakpoint >= 2)` still serializes as `(theme-breakpoint>=2)`.
- `wordpress-media-range-layer-prefixer.php` now self-tests unitless width range fallback and modern interval output inside `@layer theme.blocks`.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php`
  - no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `2 test files, 1512 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - passed.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6473 assertions, 0 failures`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Full native PHP LightningCSS lane evidence moves from `6458` to `6473` assertions with `0` failures.
- Conservative mapped coverage remains unchanged because this deepens the already represented media-query range/layer cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This slice reuses native `MediaQueryParser`, `CssMinifier`, `TransitionPrefixer`, focused PHP tests, and the lane-local WordPress media range example.

## Non-Overlap

This avoids accepted resolution prefixing, x/dppx conversion, env/calc ranges, custom feature case preservation, negated custom ranges, target browser range boundaries, redundant boolean wrappers, explicit media-condition validation, bundle/import graph, CSS Modules, source maps, CSSOM, custom at-rules, and property/value slices.

Follow-up candidate: constant CSS math function folding inside media range values, such as `max(1px,2px)`, remains distinct from this unitless length normalization patch.
