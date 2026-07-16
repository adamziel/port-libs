# Source Map Skipped Index Guard Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T015918Z`
Base accepted HEAD: `dc8bb5cac377111467dc403c9b9c75704db62cd4`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` from the pinned `Cargo.lock`.
- Source-truth files inspected locally:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs::read_relative_vlq()`

`add_vlq_map()` imports `sources`, `sourcesContent`, and `names` before decoding
raw VLQ mappings. It decodes and validates source/name indexes before deciding
whether the current generated line is skipped by a negative `line_offset`, so
bad indexes are still rejected and the imported tables remain visible after the
error.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for skipped raw VLQ mappings whose
  source index or name index is out of range after a negative generated-line
  offset.
- Extended `wordpress-source-map-vlq-offsets.php` self-test with the same
  table-preserving error behavior for block/theme source-map diagnostics.
- The existing native `SourceMap::addVlqMap()` implementation already matched
  upstream behavior; this slice adds the missing parity guard and smoke.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 448 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5463 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - passed

Focused SourceMap assertions moved from `436` to `448`. Full LightningCSS PHP
assertions moved from `5451` to `5463`. Conservative mapped coverage remains
`2297 / 3532` because this deepens the existing `parcel_sourcemap add_vlq_map`
offset cluster.

## Dependency Closure

No new support component is needed. This reuses the lane-local native Source Map
v3/Base64 VLQ implementation and adds no Node, Rust, browser service, external
source-map library, or live-service dependency.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 imports, byte-stream
no-comma parsing, positive/negative raw-map line and column offsets, skipped
same-line relative state, all-skipped table preservation, duplicate generated
column handling, unsorted line sorting, empty-span behavior, `addSourceMap()`,
`extendWithSourceMap()`, project-root normalization, JSON/data URL/buffer
round-tripping, CSS Modules, CSSOM, media-query, target-prefix, bundle/import,
or custom at-rule work. It is limited to validation of invalid raw VLQ
source/name indexes that are on generated lines skipped by a negative offset.
