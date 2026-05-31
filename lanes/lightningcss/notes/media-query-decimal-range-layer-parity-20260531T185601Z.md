# LightningCSS Media Query Decimal Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T185601Z`

Base: `0c0eec061390da3a2185ec8623476b5865dd4a49`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source:
  - `src/media_query.rs::QueryFeature::to_css`, where media range and interval values serialize through the normal value printers.
  - `src/values/number.rs::CSSNumber::to_css` and `src/values/length.rs::serialize_dimension`, where non-zero absolute values below `1` drop the leading zero, plus signs are omitted, trailing decimal zeros are removed, and zero lengths serialize as `0`.
- Local pinned native-addon oracle confirmed:
  - `@media (width >= 0.5px)` minifies to `@media (width>=.5px)`.
  - Firefox 60 fallback for `@layer blocks { @media (width >= 0.5px) ... }` serializes `@media (min-width:.5px)`.
  - Firefox 85 interval fallback for `@media (0.5px <= width <= 1.50px)` serializes `(min-width:.5px) and (max-width:1.5px)`.

## Red-First Evidence

Before this patch, PHP preserved non-upstream numeric spelling in media values:

```text
(width >= 0.5px) => (width>=0.5px) | fallback (min-width:0.5px)
(0.5px <= width <= 1.5px) => (0.5px<=width<=1.5px) | fallback (min-width:0.5px) and (max-width:1.5px)
```

The pinned upstream native addon emitted `.5px` in both no-target and fallback paths.

## Native Delta

- `MediaQueryParser::minifyValue()` now canonicalizes simple numeric media feature values after calc folding and slash compaction.
- Decimal numbers and dimensions drop leading zeros, trailing decimal zeros, and author plus signs.
- Zero non-resolution dimensions serialize as `0`, matching LightningCSS length serialization.
- Ratio endpoints normalize both sides and still reduce `/1` values, so `0.5/1.0` becomes `.5`.
- Layered target fallback output uses the same canonical values for `min-` / `max-` media feature aliases.
- Added `wordpress-media-decimal-range-layer-prefixer.php` for block-theme decimal breakpoint smoke coverage without Node/WASM at runtime.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-decimal-range-layer-prefixer.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 670 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 3156 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-decimal-range-layer-prefixer.php --self-test`
  - Result: exited `0`.
- `git diff --check -- lanes/lightningcss`
  - Result: pending final verification.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `3141 -> 3156 pass / 0 fail`.
- Conservative mapped coverage remains `1696 / 3532`; this deepens the already represented `src/lib.rs::test_media` / `src/media_query.rs` media range and legacy fallback cluster rather than claiming a new denominator row.

## Non-Overlap

This avoids accepted all-media elision, parenthesized/double negation, explicit media-type OR guards, typed/unknown/equality range parsing, invalid range validation, include/exclude feature flags, compound resolution prefix rewrites, x-resolution target serialization, calc() operator spacing, media-list/layer import validation, CSSOM, CSS Modules, source-map, bundler, custom-at-rule, target-prefix, and property-value/color/font/grid slices. It only closes numeric value serialization inside media range/layer output.

## Dependency Closure

No new support component is needed. The slice reuses the native `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, focused PHP tests, and lane-local WordPress smoke. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.

## Next Task

Continue non-overlapping LightningCSS media-query parser/serialization, CSSOM read/write, CSS Modules, SourceMap, bundler graph, target-prefix browser-boundary, property-value/color/font/grid, and custom-at-rule parity. Resolution unit default serialization remains a separate already represented target-boundary area and was not widened in this patch.
