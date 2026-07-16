# LightningCSS media query range layer parity - 2026-06-01T151941Z

Slice: `lightningcss-media-query-range-layer-parity-20260601T151941Z`

## Source truth

- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source reads:
  - `src/media_query.rs` `MediaFeatureValue::parse_unknown`, `MediaFeatureType::allows_ranges`, and `src/values/length.rs` length unit inventory.
  - `src/lib.rs::test_media` for the represented media range/layer minifier and target fallback cluster.
- Targeted pinned native-addon probes confirmed:
  - `@media (width >= 2foo)` and `@media (theme-breakpoint >= 2foo)` reject unsupported dimensions.
  - Unknown/custom feature direct resolution values such as `2dppx` still serialize, while math functions over resolution/time/angle/unknown dimensions reject.
  - CSS length units including root-relative `rcap` are valid in known and unknown range features and in unresolved `sign(calc(...))` length expressions.

## Behavior

- `MediaQueryParser` now validates range dimensions against the upstream CSS length unit table instead of accepting arbitrary alphabetic units.
- Unknown/custom range features still accept numbers, ratios, idents, CSS lengths, direct resolution values, and `env()`, but reject unsupported dimensions such as `2foo`, `2s`, and `2deg`.
- Math functions in known/unknown range values now reject invalid dimension units and resolution dimensions inside functions, while preserving valid mixed CSS length math such as `max(1em, 2px)`.
- Root-relative length units `rex`, `rch`, `rcap`, and `ric` are now included in the native CSS length-unit guard.
- The existing internal marker for unitless length-math lowering remains accepted only through the parser-owned fallback path.
- `wordpress-media-range-layer-prefixer.php` now covers a valid `rcap` range fallback, valid mixed custom length math, and invalid unsupported-dimension guards.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` - 1 test files / 732 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test` - exited 0.
- `php tools/run-tests.php lanes/lightningcss/tests` - 13 test files / 8427 assertions / 0 failures.

## Status delta

- Focused PHP assertions: `8399 -> 8427` full LightningCSS lane assertions, `+28`.
- Conservative mapped coverage remains `2393 / 3532`; this deepens the represented upstream `src/media_query.rs` and `src/lib.rs::test_media` range/layer cluster rather than claiming a new denominator row.
- Full upstream Rust/Node/WASM runners were not run in this isolated worker.

## Non-overlap

This does not repeat accepted percentage length rejection, redundant `calc()` folding, `sign()` and advanced unitless math folding, resolution `x` serialization, vendor pixel-ratio prefixing, negated/equality range handling, import graph media propagation, custom-media scanner behavior, CSSOM, CSS Modules, source-map, property-value, or target-prefix browser-boundary slices. It is limited to upstream dimension-unit validation for known and unknown layered media range values.

## Dependency closure

No new support component is needed. The slice reuses native PHP `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, focused lane tests, the existing WordPress media range/layer example, and pinned upstream source/native probes only as oracle evidence.

## Next

Continue with non-overlapping LightningCSS media-query parser/recovery, target-prefix browser-boundary cases, CSSOM, CSS Modules, SourceMap, bundler/import graph, property-value/font/grid/color, or custom-at-rule parity.
