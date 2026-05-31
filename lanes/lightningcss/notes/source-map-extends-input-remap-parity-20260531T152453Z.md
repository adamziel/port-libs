# Source Map Input Remap Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T152453Z`
Base: `92a6f092c9582e866c5b2412b97dd190e3f378da`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior:
  - `SourceMap::extends` imports original sources, names, and source contents from an input source map before remapping current generated mappings through `find_closest_mapping`.
  - A remapped mapping uses the closest original source/name location from the input map, including the upstream boundary where a query after the final segment resolves to the first line mapping.
  - If the input map has no matching line or its closest segment is generated-only, the current mapping becomes generated-only.

## Native PHP Delta

- `SourceMap::extendWithSourceMap()` ports the upstream `extends` remapping behavior with PHP-friendly naming.
- The focused SourceMap test now covers source/name/content import, exact and after-last closest mapping remaps, generated-only fallback, missing-line fallback, generated-only preservation, and VLQ decode output.
- `wordpress-source-map-vlq-offsets.php` now self-tests a theme-json generated CSS map remapped back to `theme.json` token locations.

## Verification

- Red-first focused run after adding the test failed on missing `SourceMap::extendWithSourceMap()` before implementation.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 92 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 1852 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- Root harness: not run - isolated micro-slice.

## Status

- PHP pass evidence moves from 1843 to 1852 assertions.
- Conservative mapped coverage moves from 1262 to 1265 of 3532 for three `parcel_sourcemap::SourceMap::extends` input-remap behaviors.
- Dependency closure: no new support component is needed; this extends the lane-local native Source Map v3/VLQ support and adds no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ import/remapping, generated-only segments, line/column offset import, empty-line span preservation, `offsetColumns`/`offsetLines`/`addEmptyMap`, or `addSourceMap` line-replacement behavior. It specifically covers the remaining input source-map extension/remapping path used when generated CSS maps must be traced back through an upstream input map.
