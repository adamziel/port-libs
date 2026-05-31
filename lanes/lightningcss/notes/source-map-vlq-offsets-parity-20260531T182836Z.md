# Source Map Duplicate Generated-Column Lookup Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T182836Z`
Base: `1d7de15e4e85a2b8dbfd1c80922d2921091d0371`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream files:
  - `parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()`, which permits adjacent Base64 VLQ mappings without comma separators.
  - `parcel_sourcemap-2.1.1/src/mapping_line.rs::find_closest_mapping()`, which calls Rust `binary_search_by()` over sorted generated columns.
- Source-truth inference: for raw `AAAAAA`, the first mapping is source-backed at generated column 0 and the adjacent second mapping is generated-only at the same generated column. Rust `binary_search_by()` selects the later duplicate for an exact column-0 lookup, while an after-last lookup still falls back to the first mapping at generated column 0.

## Native PHP Delta

- `SourceMap::findClosestMapping()` now uses the same Rust-compatible binary-search boundary helper already used by `offsetColumns()`.
- Exact lookups into duplicate generated columns now select the same duplicate as upstream instead of always selecting the first stored mapping.
- `extendWithSourceMap()` now inherits that behavior because it remaps through `findClosestMapping()`: exact duplicate generated-only source-map entries no longer incorrectly remap through an earlier source-backed duplicate.
- `wordpress-source-map-vlq-offsets.php` self-tests the duplicate lookup and input-map extension path for generated block/theme CSS maps.

## Verification

- Red-first probe before implementation: `SourceMap::fromJson(... "mappings":"AAAAAA" ...)->findClosestMapping(0, 0)` returned the first source-backed duplicate instead of the upstream generated-only duplicate.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 210 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 3069 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 201 to 210.
- Full LightningCSS PHP evidence moves from 3060 to 3069 pass / 0 fail.
- Conservative mapped coverage moves from 1684 to 1685 of 3532 for the additional `parcel_sourcemap::MappingLine::find_closest_mapping` duplicate generated-column behavior.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 generated-only/name imports, byte-stream VLQ import itself, negative raw-VLQ line-offset import, duplicate-column `offsetColumns()` shifting, relative VLQ guard failures, `offsetColumns()`/`offsetLines()`/`addEmptyMap()` basics, empty generated-line spans, `addSourceMap()` line replacement, `extendWithSourceMap()` source/name remapping basics, project-root normalization, JSON/data URL defaults, source/name getters, buffer round trips, bundle SourceMap source collection, CSS Modules, CSSOM, media-query, target-prefixing, or custom-at-rule work. It is limited to closest-mapping lookup parity when raw VLQ byte streams produce duplicate generated columns.
