# Source Map Mapping Record Offsets - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T004353Z`
Base: `5b87111468b46af8cd72097f10d11bf759b0ca92`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS pins `parcel_sourcemap` `2.1.1` in `Cargo.lock`.
- Source truth: `parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::add_mapping_with_offset()` offsets an existing decoded `Mapping` by signed generated-line and generated-column offsets, rejects negative or overflowed generated coordinates, and preserves the mapping's original source/name indexes.
- This slice maps that public mapping-record offset path into the native PHP `SourceMap` API instead of only exposing source-backed and generated-only specialized offset helpers.

## Native PHP Delta

- `SourceMap::addMappingRecordWithOffset()` now accepts a decoded mapping record shape from `getMappings()`/`decodeVlq()`, applies generated line and column offsets, validates unsigned 32-bit bounds, preserves source/name mappings, and rejects generated-only records with names.
- `SourceMapTest.php` adds a focused upstream parity case covering source-backed and generated-only record replay, exact VLQ output, source/name/sourceContent preservation, negative line/column offset guards, and generated-only name rejection.
- `wordpress-source-map-vlq-offsets.php` self-tests the same replay path for a generated block/theme stylesheet source map.

## Verification

- Baseline before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 388 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 401 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 5148 assertions, 0 failures.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'`: JSON OK.
- `git diff --check -- lanes/lightningcss`: no whitespace errors.

## Status

- Focused SourceMap assertions move from 388 to 401.
- Full LightningCSS PHP evidence moves from 5135 to 5148 assertions / 0 failures.
- Conservative mapped coverage moves from 2238 to 2239 of 3532 for `parcel_sourcemap::SourceMap::add_mapping_with_offset()` mapping-record offset replay.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, external source-map package, or live service.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice. This patch does not repeat accepted raw Source Map v3 import, byte-stream no-comma parsing, generated-only segments, positive or negative raw-map line/column offsets, all-skipped raw-VLQ table preservation, duplicate generated-column offset/search behavior, mapping-line sort side effects, relative VLQ overflow guards, empty generated-line spans, `addSourceMap()` replacement/consumption, input-map extension, project-root normalization, JSON/data URL defaults, buffer round trips, generated-column overflow guards, null sourcesContent guards, bundler SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule work. It is limited to the upstream mapping-record replay offset API.
