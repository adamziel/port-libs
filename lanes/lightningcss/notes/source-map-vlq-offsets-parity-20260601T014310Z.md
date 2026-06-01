# Source Map Offset Overflow Preflight Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T014310Z`
Base: `388d75493f253681c7862bcbbc85820a181fa9e0`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` from the pinned `Cargo.lock`.
- Source-truth files:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::offset_columns()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs::MappingLine::offset_columns()`
- Upstream computes the requested `start_column` and returns a `SourceMapError` for unsigned 32-bit overflow before calling `MappingLine::ensure_sorted()`. An invalid offset must not sort or otherwise mutate an unsorted raw VLQ mapping line.

## Native PHP Delta

- `SourceMap::offsetColumns()` now preflights start-column overflow before sorting the target generated line.
- `SourceMapTest.php` pins the unsorted raw VLQ case `UAAAA,RACAC`: the failed `offsetColumns(0, 4294967295, 1)` call leaves stored generated columns in input order `[10, 2]`.
- `wordpress-source-map-vlq-offsets.php` self-tests the same guard for generated block/theme source-map diagnostics.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record one conservative mapped source-map behavior and the focused assertion delta.

## Verification

- Baseline before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 426 assertions, 0 failures.
- Red probe before implementation: invalid `offsetColumns(0, 4294967295, 1)` changed unsorted generated columns from `[10,2]` to `[2,10]`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 430 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 5390 assertions, 0 failures.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 426 to 430.
- Full LightningCSS PHP evidence moves from 5386 to 5390 assertions / 0 failures.
- Conservative mapped coverage moves from `2289 / 3532` to `2290 / 3532`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native Source Map v3/Base64 VLQ implementation and does not require Node, Rust, a browser service, an external source-map package, or live-service credentials.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 import, byte-stream no-comma parsing, generated-only segments, positive or negative raw-map line/column offsets, all-skipped raw-VLQ table preservation, duplicate generated-column offset/search behavior, sorted raw generated-column serialization, nested `addSourceMap()` unsorted child-line behavior, direct `offsetLines()` unsorted movement, empty-line column no-ops/overflow guards, shifted-column overflow guards after sorted serialization, past-EOF line spans, input-map extension, project-root normalization, JSON/data URL defaults, buffer round trips, bundler SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule work. It is limited to invalid `offset_columns` start-column overflow preserving unsorted raw-VLQ line state by failing before the upstream sort entrypoint.
