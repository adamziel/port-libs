# LightningCSS Media Query Math Function Range Layer Parity - 2026-06-01

Slice: `lightningcss-media-query-range-layer-parity-20260601T073023Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/values/calc.rs::Calc::parse_with()` reduces comparable `min()` / `max()` arguments and resolves comparable `clamp()` centers before printing.
- `src/media_query.rs::MediaFeatureValue::parse()` sends length and number media feature values through those typed value parsers.
- `src/lib.rs::test_media` already preserves mixed-unit `max(10px, 1rem)` media ranges; this slice adds the same parser behavior for comparable same-unit math functions inside layered media range fallbacks.

## Red-First Evidence

Current-base probes before the patch preserved comparable math functions inside media range values:

- `(width > max(10px, 20px))` serialized as `(width>max(10px,20px))`.
- Legacy fallback for `(width > max(10px, 20px))` serialized as `not (max-width:max(10px,20px))`.
- `(-webkit-device-pixel-ratio >= max(1, 2))` serialized as `(-webkit-device-pixel-ratio>=max(1,2))`.

## Implementation

- `MediaQueryParser::minifyValue()` now folds simple comparable `min()`, `max()`, and `clamp()` values for length, number, and unknown media feature values.
- The fold reuses existing `calc()` simplification for nested arguments and only reduces values that share a directly comparable unit.
- Mixed-unit media math such as `max(10px, 1rem)` remains preserved and minified, matching the existing upstream media test.
- Layered target fallback output now receives the reduced value before legacy `min-` / `max-` feature serialization.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `2 test files, 1574 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - exited `0`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6735 assertions, 0 failures`

Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moved from `6721` to `6735` assertions with `0` failures.
- Conservative mapped coverage remains `2360 / 3532` because this deepens the already represented media-query range/layer and CSS math parser clusters rather than claiming a new upstream inventory row.

## Non-Overlap

This does not repeat accepted media range target boundaries, resolution prefixing, x/dppx conversion, unitless lengths, calc spacing/folding, env() resolution handling, invalid feature value validation, custom-media import scanner behavior, CSS Modules, source-map, CSSOM, bundle/import graph, custom at-rule, or property-value target-prefix work.

## Dependency Closure

No new support component is needed. This reuses native PHP `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, focused tests, and the lane-local WordPress media range layer example. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Next Task

Continue with non-overlapping media-query parser recovery/custom-media expansion boundaries, source-map, CSS Modules, bundle/import graph, property-value, CSSOM, custom at-rule, or target-prefix parity.
