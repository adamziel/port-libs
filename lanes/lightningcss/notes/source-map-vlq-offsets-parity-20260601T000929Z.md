# Source Map Mapping-Line Sort Side Effects - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T000521Z`
Base: `9938ea0ca5f2430c11f7b91d23d2213507185488`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` from pinned `Cargo.lock`.
- Source truth:
  - `parcel_sourcemap-2.1.1/src/mapping_line.rs::add_mapping()` stores same-line mappings in input order and marks the line unsorted when a later generated column is lower than the previous column.
  - `MappingLine::ensure_sorted()` sorts only when called by `write_vlq()`, `find_closest_mapping()`, or `offset_columns()`.
  - `SourceMap::get_mappings()` iterates stored mapping lines and does not sort by generated column on its own.
- Focused parity case: raw VLQ `UAAAA,RACAC` stores generated columns `10, 2` until one of the upstream sort entry points canonicalizes the line to `2, 10`.

## Native PHP Delta

- `SourceMap::getMappings()` now exposes stored per-line order instead of sorting by generated column.
- `SourceMap::writeVlq()`, `findClosestMapping()`, and `offsetColumns()` now sort affected generated lines in place, matching the upstream side effect.
- `SourceMapTest.php` covers raw read order, `writeVlq()` sorting, zero-offset `offsetColumns()` sorting, closest-lookup sorting, and positive offset behavior after sorting.
- `wordpress-source-map-vlq-offsets.php` self-tests the same generated-column order transitions for block/theme source maps.

## Verification

- Baseline before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 363 assertions, 0 failures.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 377 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 5030 assertions, 0 failures.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 363 to 377.
- Full LightningCSS PHP evidence moves from 5016 to 5030 assertions / 0 failures.
- Conservative mapped coverage moves from 2218 to 2219 of 3532 for `parcel_sourcemap::MappingLine::ensure_sorted` side effects before `write_vlq()`, `find_closest_mapping()`, and `offset_columns()`.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, external source-map package, or live service.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 import, byte-stream no-comma parsing, generated-only segments, positive or negative raw-map line/column offsets, all-skipped raw-VLQ table preservation, duplicate generated-column offset/search behavior, relative VLQ overflow guards, empty generated-line spans, `addSourceMap()` replacement/consumption, input-map extension, project-root normalization, JSON/data URL defaults, buffer round trips, generated-column overflow guards, null sourcesContent guards, bundler SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule work. It is limited to the missing mapping-line sort side effects around out-of-order raw VLQ generated columns.
