# Source Map Unsorted Trailing Window VLQ Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T070224Z`
Base accepted HEAD: `cc9294ac19877407e3f202dbdfd54b6a9a8fb67d`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates Source Map v3/VLQ mutation to `parcel_sourcemap 2.1.1`.
- Source-truth files inspected locally:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

`MappingLine::offset_columns()` sorts a mapping line before binary-searching
the requested column and, for negative offsets, drains the window between
`generated_column + offset` and the insertion point. This slice pins the edge
where the raw VLQ line starts unsorted and the requested generated column is
beyond the final segment, so the trailing window is drained without appending
or shifting a new mapping.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for raw VLQ `UAAAA,RACAC`, whose
  raw read order is generated columns `[10, 2]`.
- Verified `offsetColumns(0, 20, -15)` sorts first, drains the trailing column
  10 mapping, preserves the earlier column 2 source/name entry, and keeps
  upstream after-last closest lookup semantics.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same
  WordPress block/theme source-map path.
- Updated lane-local status and manifest evidence. Conservative mapped
  coverage remains `2360 / 3532` because this deepens the already represented
  `parcel_sourcemap` VLQ offset cluster.

## Verification

- Baseline focused source-map evidence before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 659 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php` ->
  no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 669 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` ->
  `13 test files, 6562 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'`
  -> `JSON OK`.
- `git diff --check -- lanes/lightningcss` -> clean.
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
invalid-index mutation, duplicate generated-column offsets/search, sorted
trailing negative-window drain behavior, direct unsorted write/lookup/line
offset behavior, empty-span merging, `addSourceMap()` consumption,
`extendWithSourceMap()`, project-root normalization, JSON/data URL defaults,
buffer-unsorted behavior, CSS Modules, CSSOM, media-query, target-prefixing,
bundle/import graph, property-value, or custom-at-rule work. It is limited to
unsorted raw-VLQ line sorting before a trailing negative column-window drain.
