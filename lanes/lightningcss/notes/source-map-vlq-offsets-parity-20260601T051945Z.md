# Source Map Buffered Unsorted VLQ Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T051945Z`
Base accepted HEAD: `018b45ef9c6dbc5953c310812969453e7fb8e5dd`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map behavior to `parcel_sourcemap 2.1.1`.
- Source-truth files inspected locally:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

`SourceMap::to_buffer()` stores the raw `SourceMapInner` mapping lines, so an
unsorted raw-VLQ mapping line remains unsorted after `from_buffer()` until an
upstream sort entrypoint such as `write_vlq()`, `find_closest_mapping()`, or
`offset_columns()`. A buffered child map merged with `add_sourcemap()` is still
consumed after its mapping line replaces the parent line at the generated-line
offset.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for raw VLQ `UAAAA,RACAC`
  round-tripped through `toBuffer()` / `fromBuffer()` while preserving input
  order `[10, 2]` until `writeVlq()` sorts it to `[2, 10]`.
- Covered positive and negative `offsetColumns()` on the restored buffer.
- Covered `addSourceMap()` with the buffered unsorted child, including parent
  line replacement, source/name table preservation, sorted VLQ output, and child
  consumption.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same
  build-free WordPress theme source-map path.

## Verification

- Baseline focused source-map evidence before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 576 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php` ->
  no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 593 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` ->
  `13 test files, 6152 assertions, 0 failures`.

Final JSON validation and `git diff --check -- lanes/lightningcss` are recorded
in the handoff response.

## Status

- Focused SourceMap assertions move from `576` to `593`.
- Full LightningCSS PHP evidence moves from `6135` to `6152` assertions.
- Conservative mapped coverage remains `2353 / 3532`; this deepens the already
  represented `parcel_sourcemap` SourceMap buffer/unsorted raw-VLQ cluster.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP Source
Map v3/Base64 VLQ implementation and adds no Node, Rust runtime, WASM, browser
service, external source-map package, or live-service dependency.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated to this source-map slice. This patch does not repeat accepted raw
Source Map v3 import, byte-stream no-comma parsing, generated-only segments,
positive or negative raw-map line/column offsets, all-skipped table
preservation, skipped invalid-index guards, partial invalid-index mutation,
duplicate generated-column offsets/search, unsorted direct write/lookup/line
offset behavior, empty-span merging, `extendWithSourceMap()`, project-root
normalization, JSON/data URL defaults, CSS Modules, CSSOM, media-query,
target-prefixing, bundle/import graph, property-value, or custom-at-rule work.
It is limited to buffered unsorted raw-VLQ state through buffer round-trip,
offset, and nested source-map merge entrypoints.
