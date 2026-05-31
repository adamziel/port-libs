# Source Map Unsorted VLQ Column Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T233122Z`
Base: `a364d07040190b68b467cd69fb969339b783a7fe`

## Upstream Evidence

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` from the pinned `Cargo.lock`.
- Source truth:
  - `parcel_sourcemap-2.1.1/src/mapping_line.rs::add_mapping()` marks a mapping line unsorted when a later generated column is lower than the previous one.
  - `MappingLine::ensure_sorted()` sorts line mappings before `write_vlq()`, `find_closest_mapping()`, and `offset_columns()`.
  - `MappingLine::offset_columns()` calls `ensure_sorted()` before binary-searching and draining/shifting mappings.
- Focused parity case: raw VLQ `UAAAA,RACAC` decodes in input order as generated columns `10` then `2`; upstream sorts the line before writing and before column-offset mutation.

## Native PHP Delta

- `SourceMapTest.php` now pins sorted serialization for out-of-order raw VLQ source-backed mappings, including name/original deltas after sort.
- The same test covers positive `offsetColumns(0, 5, 3)` and negative `offsetColumns(0, 10, -8)` after sorting.
- `wordpress-source-map-vlq-offsets.php` self-tests the same raw VLQ sorting and offset behavior for generated theme/block CSS source maps.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record the focused assertion and conservative mapped-coverage movement.

## Verification

- Baseline before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 341 assertions, 0 failures.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 355 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 4891 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 341 to 355.
- Full LightningCSS PHP evidence is 13 files / 4891 assertions / 0 failures.
- Conservative mapped coverage moves from 2198 to 2199 of 3532 for `parcel_sourcemap::MappingLine::ensure_sorted` behavior before `write_vlq()` and `offset_columns()`.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, external source-map package, or live service.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 import, raw byte-stream no-comma parsing, generated-only segments, positive or negative raw-map line/column offsets, all-skipped raw-VLQ table preservation, duplicate generated-column offset/search behavior, relative VLQ overflow guards, empty generated-line spans, `addSourceMap()` replacement/consumption, input-map extension, project-root normalization, JSON/data URL defaults, buffer round trips, generated-column overflow guards, bundler SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule work. It is limited to sorted line semantics when raw VLQ segments arrive with decreasing generated columns and are then serialized or offset.
