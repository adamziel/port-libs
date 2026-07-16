# Source Map Buffered Duplicate Column Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T055116Z`
Base accepted HEAD: `7db0bee1b6d6b17fcc1ae3a0e1b10ac7a87ade2d`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates Source Map v3/VLQ behavior to `parcel_sourcemap 2.1.1`.
- Source-truth files inspected locally:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

`MappingLine::offset_columns()` sorts the line and uses Rust binary search to
start shifting at the matched duplicate generated-column boundary. Since
`SourceMap::to_buffer()` stores the raw `SourceMapInner`, that duplicate-column
boundary must survive buffer restore and then remain observable when a buffered
child map replaces a parent line through `add_sourcemap()`.

## Native PHP Delta

- Added `SourceMapTest.php` coverage for raw VLQ `AAAAAA,CACAC`, which decodes
  to a source-backed mapping, a same-column generated-only mapping, and a later
  source-backed mapping.
- Covered `toBuffer()` / `fromBuffer()` preservation before
  `offsetColumns(0, 0, 5)` shifts only from the upstream duplicate-column match
  boundary.
- Covered the same buffered child through `addSourceMap(..., 1)`, verifying
  parent-line replacement, child consumption, source/name table preservation,
  and a subsequent `offsetColumns(1, 0, 3)` mutation.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same
  build-free WordPress theme source-map path.

## Verification

- Baseline focused source-map evidence before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 603 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php` ->
  no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 621 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` ->
  `13 test files, 6340 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'`
  -> `JSON OK`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status

- Focused SourceMap assertions move from `603` to `621`.
- Full LightningCSS PHP evidence moves from `6322` to `6340` assertions.
- Conservative mapped coverage remains `2359 / 3532`; this deepens the already
  represented `parcel_sourcemap` duplicate generated-column / buffer / nested
  source-map offset cluster.
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
invalid-index mutation, unsorted direct write/lookup/line-offset behavior,
empty-span merging, `extendWithSourceMap()`, project-root normalization,
JSON/data URL defaults, existing buffer-unsorted behavior, CSS Modules, CSSOM,
media-query, target-prefixing, bundle/import graph, property-value, or
custom-at-rule work.
