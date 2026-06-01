# LightningCSS Media Query Numeric Calc Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T003549Z`
Base accepted HEAD: `5b87111468b46af8cd72097f10d11bf759b0ca92`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/media_query.rs::MediaFeatureValue::parse_known` dispatches typed media feature values.
- `src/values/number.rs::CSSNumber::parse` computes numeric `calc()` values for number-valued media features.
- `src/values/number.rs::CSSInteger::parse` still expects a concrete integer token and has `TODO: calc??`, so integer-valued media features reject `calc()`.
- `src/values/resolution.rs::Resolution::parse` still expects a concrete resolution dimension and has `TODO: calc?`, so resolution-valued media features reject `calc()`.
- `src/media_query.rs::write_min_max` lowers range syntax to min/max legacy feature forms, including the vendor device-pixel-ratio aliases used by old Firefox/WebKit targets.

## Native Delta

- `MediaQueryParser` now folds simple unitless numeric `calc()` values such as `calc(1 + 1)` for number-valued media ranges.
- Layered device-pixel-ratio ranges lower through existing legacy target fallback paths:
  - `(-webkit-device-pixel-ratio >= calc(1 + 1))` becomes `(-webkit-min-device-pixel-ratio:2)` for old Firefox fallback output.
  - `(1 <= -moz-device-pixel-ratio <= calc(1 + 1))` becomes `(min--moz-device-pixel-ratio:1) and (max--moz-device-pixel-ratio:2)`.
- Integer and resolution media ranges now reject `calc()` values before minification/prefixing, matching the pinned upstream parser split.
- `wordpress-media-range-layer-prefixer.php` now covers layered numeric calc range fallback output and integer/resolution calc guard failures.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `1 test files, 363 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 818 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - exited `0`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5148 assertions, 0 failures`

Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.

## Status Delta

- Native PHP full-lane evidence moved from `5135` to `5148` assertions.
- Conservative mapped coverage remains `2238 / 3532` because this deepens the already represented media-query range/layer cluster instead of adding a new denominator row.

## Non-Overlap

- This patch does not repeat accepted resolution equality prefix fallbacks, feature-flag range printing, interval/range lowering, media comment handling, bare-not/layer validation, placeholder target-prefixing, or resolution media layer fallback slices.
- A stale custom-media import-tail rework note in the main handoff directory was inspected and excluded as unrelated to this numeric media-feature calc range/layer behavior.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP media-query parser, target prefixer, CSS minifier, and existing WordPress-relevant example smoke.
