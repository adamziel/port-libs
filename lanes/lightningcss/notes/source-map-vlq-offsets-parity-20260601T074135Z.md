# Source Map First-Segment Fallback Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T074135Z`
Base accepted HEAD: `0e6b89c861545d2e8159ac2fd07a33034e44e234`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates Source Map v3/VLQ mutation and lookup behavior to `parcel_sourcemap 2.1.1`.
- Source-truth files inspected locally:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`

`MappingLine::find_closest_mapping()` sorts a line, binary-searches the target
generated column, and when the lookup falls after the final segment returns the
first sorted segment at generated column `0`. If that first segment is
generated-only, an after-last lookup stays generated-only even when a later
source-backed segment exists. `offset_columns()` preserves that fallback after
moving later mappings.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for raw VLQ `A,KAAA`: a
  generated-only first segment followed by a source-backed segment.
- Verified after-last closest lookup before and after `offsetColumns(0, 5, 3)`
  returns the generated-only first segment while exact lookup still finds the
  shifted source-backed mapping.
- Verified `extendWithSourceMap()` converts a generated mapping to
  generated-only when the input map's after-last closest lookup resolves to
  that generated-only first segment.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same
  block/theme source-map path.

## Verification

- Baseline focused source-map evidence before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 669 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php` ->
  no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 682 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` ->
  `13 test files, 6780 assertions, 0 failures`.
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
generated-only segment import, positive or negative raw-map line/column
offsets, all-skipped table preservation, skipped invalid-index guards, partial
invalid-index mutation, duplicate generated-column offset/search, sorted or
unsorted trailing negative-window drains, direct unsorted write/lookup/line
offset behavior, empty-span merging, `addSourceMap()` consumption,
`extendWithSourceMap()` source-backed after-last remapping, project-root
normalization, JSON/data URL defaults, buffer-unsorted behavior, CSS Modules,
CSSOM, media-query, target-prefixing, bundle/import graph, property-value, or
custom-at-rule work. It is limited to after-last closest fallback when the
first sorted segment is generated-only and later source-backed mappings move
under generated-column offsets.
