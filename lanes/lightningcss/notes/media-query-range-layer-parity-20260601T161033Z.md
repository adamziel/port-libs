# LightningCSS media query range layer parity - 2026-06-01T161033Z

Slice: `lightningcss-media-query-range-layer-parity-20260601T161033Z`

## Source truth

- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source reads:
  - `src/media_query.rs` `MediaFeatureValue::parse_known` and range feature value parsing.
  - `src/values/calc.rs` math function parsing for trigonometric value functions.
  - `src/lib.rs::test_media` for represented media minifier and range fallback cases.
- Targeted pinned native-addon probes confirmed:
  - `sin()`, `cos()`, and `tan()` fold in number-style media range values such as `(width >= sin(1rad))`, `(width >= sin(90deg))`, `(theme-breakpoint >= cos(0deg))`, `(aspect-ratio >= tan(45deg))`, and `(-webkit-device-pixel-ratio >= sin(90deg))`.
  - Nested trig values fold inside other math functions, including `max(sin(1rad), 4px)` and `hypot(sin(1rad), cos(1rad))`.
  - Inverse trig functions such as `asin()` and `atan2()` still reject in media range values at this pinned commit.
  - Invalid trig arguments such as `sin(1px)`, `sin(10%)`, and `sin(var(--angle))` reject instead of being serialized.

## Behavior

- `MediaQueryParser` now recognizes `sin()`, `cos()`, and `tan()` in the existing media math function inventory.
- Trig arguments support direct numbers, CSS angles (`deg`, `grad`, `rad`, `turn`), `pi`/`e`, simple `calc()` arithmetic, and nested foldable unitless math.
- Layered media range minification now serializes upstream-compatible unitless trig results, including nested math such as `max(.841471,4px)`.
- Legacy target fallback lowering preserves unitless trig range results, e.g. `(width >= sin(90deg))` lowers to `(min-width:1)` instead of adding `px`.
- The WordPress media range/layer example now covers trig fallback output and an invalid trig argument guard.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` - 1 test files / 757 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test` - exited 0.
- `php tools/run-tests.php lanes/lightningcss/tests` - 13 test files / 8650 assertions / 0 failures.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'` - JSON ok.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR);'` - JSON ok.
- `git diff --check -- lanes/lightningcss` - exited 0.

## Status delta

- Focused media parser assertions: `732 -> 757`, `+25`.
- Full LightningCSS lane assertions: `8625 -> 8650`, `+25`.
- Conservative mapped coverage remains `2398 / 3532`; this deepens the represented upstream `src/media_query.rs`, `src/values/calc.rs`, and `src/lib.rs::test_media` range/layer cluster rather than claiming a new denominator row.
- Full upstream Rust/Node/WASM runners were not run in this isolated worker.

## Non-overlap

This does not repeat accepted dimension-unit validation, redundant `calc()` folding, `sign()` range behavior, advanced `sqrt()`/`pow()`/`log()`/`exp()` math folding, resolution `x` serialization, vendor pixel-ratio prefixing, negated/equality range handling, import graph media propagation, custom-media scanner behavior, CSSOM, CSS Modules, source-map, property-value, or target-prefix browser-boundary slices. It is limited to upstream trigonometric math function parity for layered media range values.

## Dependency closure

No new support component is needed. The slice reuses native PHP `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, focused lane tests, the existing WordPress media range/layer example, and pinned upstream source/native probes only as oracle evidence.

## Next

Continue with non-overlapping LightningCSS media-query parser/recovery, target-prefix browser-boundary cases, CSSOM, CSS Modules, SourceMap, bundler/import graph, property-value/font/grid/color, or custom-at-rule parity.
