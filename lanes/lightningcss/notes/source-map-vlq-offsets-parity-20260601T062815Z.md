# Source Map Trailing Column Window Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T062815Z`
Base accepted HEAD: `cc1b0ff669a7347b4e43610b8425ed481a9b7e5c`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates Source Map v3/VLQ behavior to `parcel_sourcemap 2.1.1`.
- Source-truth files inspected locally:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

`MappingLine::offset_columns()` sorts a mapping line, binary-searches the
requested generated-column insertion point, and for negative offsets drains the
window between `generated_column + offset` and the insertion point. When that
insertion point is beyond the final segment, upstream drains the trailing
window and shifts no later mappings.

## Native PHP Delta

- Added `SourceMapTest.php` coverage for a three-segment line at generated
  columns `0`, `10`, and `20`; `offsetColumns(0, 30, -15)` drains the trailing
  `20` column mapping without appending a new segment.
- Verified that the source/name tables keep the drained mapping's name, matching
  upstream table-preservation behavior.
- Verified that a later positive offset beyond the final surviving segment is a
  no-op.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same
  block/theme source-map path.

The native `SourceMap::offsetColumns()` implementation already matched this
bounded upstream behavior; this slice adds focused parity and WordPress example
coverage.

## Verification

- Baseline focused source-map evidence before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 634 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 641 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` ->
  `13 test files, 6436 assertions, 0 failures`.
- Final PHP lint, JSON validation, and `git diff --check -- lanes/lightningcss`
  are recorded in the handoff response.

## Status

- Focused SourceMap assertions move from `634` to `641`.
- Full LightningCSS PHP evidence moves from `6429` to `6436` assertions.
- Conservative mapped coverage remains `2359 / 3532`; this deepens the already
  represented `parcel_sourcemap` VLQ column-offset cluster.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP Source
Map v3/Base64 VLQ implementation and adds no Node, Rust runtime, WASM, browser
service, external source-map package, or live-service dependency.

## Non-Overlap

No current LightningCSS rework note existed for this lane. This slice does not
repeat accepted raw Source Map v3 import, byte-stream no-comma parsing,
generated-only segments, positive or negative raw-map line/column offsets,
all-skipped table preservation, skipped invalid-index guards, partial
invalid-index mutation, duplicate generated-column offsets/search, unsorted
direct write/lookup/line-offset behavior, empty-span merging,
`addSourceMap()` consumption, `extendWithSourceMap()`, project-root
normalization, JSON/data URL defaults, existing buffer-unsorted behavior, CSS
Modules, CSSOM, media-query, target-prefixing, bundle/import graph,
property-value, or custom-at-rule work. It is limited to trailing negative
column-window drain semantics when the requested offset point is beyond the
final generated-column segment.
