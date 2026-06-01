# LightningCSS Media Query Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T060527Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source files:
  - `/home/claude/port-libs/.upstream-cache/lightningcss/src/media_query.rs`
  - `/home/claude/port-libs/.upstream-cache/lightningcss/src/lib.rs`
- Relevant upstream behavior:
  - `MediaList::transform_resolution()` applies target resolution-unit conversion across parsed media conditions.
  - `QueryFeature::Interval` serializes both interval bounds, so value-first intervals such as `0.5dppx <= resolution <= 1.5dppx` must keep both the left value and `resolution` feature after unit rewriting.
  - `test_resolution()` covers target-driven `dppx` / `x` / `dpi` resolution serialization. This slice extends the PHP parity mapping to the interval path that uses the same upstream `Resolution` value conversion.

## Implementation

- Fixed `MediaQueryParser::convertDppxResolutionUnits()` and `convertXResolutionUnits()` to capture the value in value-first resolution ranges before rewriting units.
- Added parser coverage for modern interval conversion in both directions:
  - `(.5dppx <= resolution <= 1.5dppx)` to `(.5x<=resolution<=1.5x)`
  - `(.5x <= resolution <= 1.5x)` to `(.5dppx<=resolution<=1.5dppx)`
  - direct value-first simple comparisons without pre-minification.
- Added target-prefixer coverage for a layered Firefox 102 modern interval, which previously corrupted the prelude to `(0x<=1.5x)`.
- Updated the WordPress media range layer example with the modern x-unit interval scenario.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `1 test files, 453 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1033 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - exited `0`
- Full lane/root harness:
  - not run for this isolated micro-slice

## Non-Overlap

This does not repeat accepted resolution prefix fallback coverage, `@layer` range fallback lowering, media boolean parsing, JSON/CSS Modules/CSSOM/source-map work, or the existing Safari/Firefox legacy `-webkit-` / `-moz-` resolution fallbacks. The patch is limited to value-first resolution unit conversion when the target keeps modern range/interval syntax and prefers the `x` resolution unit.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP media-query parser, target option calculation, transition prefixer media-prelude rewrite path, and WordPress example smoke harness.
