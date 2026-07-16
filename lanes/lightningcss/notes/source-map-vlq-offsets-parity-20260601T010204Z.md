# Source Map Direct Line-Offset Unsorted VLQ Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T010204Z`
Base: `e274bccd68de6d0be207ea53c6e2f938b9cd38dd`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` from the pinned `Cargo.lock`.
- Source-truth files:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::offset_lines()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs::ensure_sorted()`
- Upstream `offset_lines()` inserts or drains whole `MappingLine` records, so an unsorted raw-VLQ line stays in stored order when moved. Sorting remains a side effect of `write_vlq()`, `find_closest_mapping()`, or `offset_columns()`.

## Native PHP Delta

- `SourceMapTest.php` now pins direct positive and negative `offsetLines()` movement for raw VLQ `UAAAA,RACAC`: stored generated columns stay `[10, 2]` after line movement, then serialize or lookup as sorted columns `[2, 10]`.
- `wordpress-source-map-vlq-offsets.php` self-tests the same line-offset behavior for generated block/theme source maps.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record this as assertion growth inside the already represented source-map offset/sort cluster.

## Verification

- Baseline before this patch: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 400 assertions, 0 failures.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 413 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 5260 assertions, 0 failures.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 400 to 413.
- Full LightningCSS PHP evidence moves from 5247 to 5260 assertions / 0 failures.
- Conservative mapped coverage remains `2248 / 3532`; this deepens direct `SourceMap::offset_lines` and `MappingLine::ensure_sorted` behavior already represented in the denominator.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native Source Map v3/Base64 VLQ implementation and does not require Node, Rust, a browser service, an external source-map package, or live-service credentials.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 import, byte-stream no-comma parsing, generated-only segments, positive or negative raw-map line/column offsets, all-skipped raw-VLQ table preservation, duplicate generated-column offset/search behavior, unsorted raw generated-column sorting before direct write/offset, nested `addSourceMap()` unsorted child-line behavior, empty-line column no-ops/overflow guards, shifted-column overflow guards, past-EOF line spans, input-map extension, project-root normalization, JSON/data URL defaults, buffer round trips, bundler SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule work. It is limited to direct `offsetLines()` preserving unsorted raw-VLQ mapping-line state across generated-line movement until the upstream sort entrypoints run.
