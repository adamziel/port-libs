# Source Map Shifted Column Overflow Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T224044Z`
Base: `33a65237308053a0654b3629f3bffe8d77c73515`

## Upstream Evidence

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` 2.1.1 from the pinned `Cargo.lock`.
- Source truth: `parcel_sourcemap-2.1.1/src/mapping_line.rs::offset_columns()` stores generated columns as `u32` and shifts existing line mappings in-place after `binary_search_by()`.
- Local upstream probe against the pinned crate with overflow checks enabled panicked at `mapping_line.rs:117` when shifting an existing `u32::MAX` generated column by `+1`; the prior PHP implementation stored `4294967296` and emitted a non-u32 VLQ coordinate.

## Native PHP Delta

- `SourceMap::offsetColumns()` now preflights every positively shifted mapping before mutation and rejects shifts that would move an existing generated column past `4294967295`.
- `SourceMapTest.php` pins the guard and verifies the map's mappings and VLQ output remain unchanged after the rejected shift.
- `wordpress-source-map-vlq-offsets.php` self-tests the same guard for generated block/theme source-map diagnostics.

## Verification

- Red-first PHP probe before implementation: a map with a mapping at generated column `4294967295` followed by `offsetColumns(0, 0, 1)` stored generated column `4294967296`.
- `php -l lanes/lightningcss/src/SourceMap.php && php -l lanes/lightningcss/tests/SourceMapTest.php && php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 339 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 4683 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 336 to 339.
- Full LightningCSS PHP evidence moves from 4680 to 4683 assertions / 0 failures.
- Conservative mapped coverage moves from 2173 to 2174 of 3532 for `parcel_sourcemap::MappingLine::offset_columns` shifted generated-column overflow behavior.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, external source-map package, or live service.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 import, line/column raw-map import offsets, negative raw-VLQ line/column offsets, all-skipped VLQ table preservation, duplicate generated-column offset/search behavior, empty-line column no-ops/overflow guards, past-EOF line spans, `addSourceMap()` replacement/consumption, input-map extension, project-root normalization, JSON/data URL defaults, buffer round trips, bundler SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule work. The stale May 25 `CustomMediaTransformer` rework note was inspected and is unrelated.
