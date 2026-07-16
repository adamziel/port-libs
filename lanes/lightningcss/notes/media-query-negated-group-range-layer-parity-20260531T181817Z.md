# LightningCSS Media Query Negated Group Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T181817Z`

Base: `f239ae84229f0ac8ecc07e38ef32523b43f8024f`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source:
  - `src/media_query.rs::MediaCondition::to_css`, where `Not(Operation(...))` serializes the operation as one parenthesized condition.
  - `src/media_query.rs::QueryFeature::to_css` and `write_min_max`, where range syntax fallback writes simple `<` / `>` ranges as negated min/max features and interval ranges as grouped min/max comparisons.
  - `src/lib.rs::test_media`, which is the accepted upstream range syntax, interval fallback, and media-query prefix/minifier cluster.

## Native PHP Change

- `MediaQueryParser::lowerRangeSyntaxCondition()` now lowers the unwrapped condition inside `not (...)` when the inner condition is a parenthesized boolean operation.
- `MediaQueryParser::lowerRangeFeature()` now ignores strings that contain top-level `and` / `or` operations, preventing a grouped condition such as `(100px <= width <= 200px) or (hover)` from being misread as one malformed interval feature.
- Added focused parser and transition-prefixer assertions for layered `not ((width < 240px) or (hover))` and `not ((100px <= width <= 200px) or (hover))`.
- Updated `wordpress-media-range-layer-prefixer.php` with a negated grouped range smoke for block-theme CSS delivery.

## Red-First Evidence

- Before implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Failed with `2 test files, 576 assertions, 2 failures`.
  - The malformed interval output was:
    `@layer blocks{@media not ((min-width:(100px) and (max-width:200px) or (hover))){.wp-block-query{color:#ff0}}}`.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `2 test files, 593 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 2927 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Exits 0 and emits the checked range/layer outputs.
- Syntax and diff checks are recorded in the final worker response.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2923 -> 2927 pass / 0 fail`.
- Conservative mapped coverage remains `1645 / 3532`; this deepens the already represented `src/lib.rs::test_media` / `src/media_query.rs` media range fallback cluster rather than claiming a new denominator row.

## Non-Overlap

- Does not repeat accepted media calc() spacing, parenthesized simple negation, all-media elision, typed/unknown/equality range validation, include/exclude feature flags, x-resolution units, compound resolution prefix rewrites, bundler media boolean conjunction, layer import validation, CSSOM, CSS Modules, source-map, color/font/grid/property-value, or custom at-rule visitor slices.
- This slice is only the negated grouped boolean range lowering path inside layered media target fallbacks.

## Dependency Closure

No new support component is needed. The slice reuses the native `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, target fallback helpers, and lane-local tests/examples. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.

## Next Task

Continue with non-overlapping media-query parser recovery/serialization, CSSOM shorthand/location gaps, CSS Modules selector/prelude option boundaries, SourceMap/bundler graph edges, target-prefix browser boundaries, custom-at-rule visitors, and property-value/font/grid/color parity.
