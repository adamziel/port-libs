# Source Map Nested Unsorted add_sourcemap Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T002506Z`
Base: `5b87111468b46af8cd72097f10d11bf759b0ca92`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` from the pinned `Cargo.lock`.
- Source-truth files:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::add_sourcemap()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs::ensure_sorted()`
- Upstream `add_sourcemap()` moves each child `MappingLine` into the target line without sorting it, and drains the child map. `write_vlq()` and `find_closest_mapping()` are the sort entrypoints.
- Local cached-crate probe:
  - child raw VLQ `UAAAA,RACAC`, added to the parent with `line_offset = 2`, reads generated columns `[10, 2]` before serialization.
  - `write_vlq()` emits `;;EACAC,QADAD`, then `get_mappings()` reads `[2, 10]`.
  - The child map is empty after merge.

## Native PHP Delta

- `SourceMapTest.php` now pins nested raw-VLQ child map behavior through `addSourceMap(..., 2)`: unsorted generated-column read order is preserved until `writeVlq()` or `findClosestMapping()` sorts the line, source/name remapping remains intact, and the child map is consumed.
- `wordpress-source-map-vlq-offsets.php` self-tests the same behavior for a generated block/theme source map.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record this as a conservative deepening of the already represented `add_sourcemap` and `MappingLine` sort clusters, not a new mapped denominator row.

## Verification

- Baseline before this patch: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 388 assertions, 0 failures.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'`: JSON OK.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 400 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 5147 assertions, 0 failures.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Root harness: not run - isolated micro-slice.
- Conservative mapped coverage remains `2238 / 3532`; this adds parity assertions inside an already mapped source-map denominator cluster.

## Dependency Closure

No new support component is needed. This reuses the lane-local native Source Map v3/Base64 VLQ implementation and does not require Node, Rust, a browser service, an external source-map package, or live-service credentials.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 import, byte-stream no-comma parsing, generated-only segments, positive or negative raw-map line/column offsets, all-skipped raw-VLQ table preservation, duplicate generated-column offset/search behavior, unsorted raw generated-column sorting before direct write/offset, empty-line column no-ops/overflow guards, shifted-column overflow guards, past-EOF line spans, `addSourceMap()` replacement/consumption without unsorted child line state, input-map extension, project-root normalization, JSON/data URL defaults, buffer round trips, bundler SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule work. It is limited to nested `add_sourcemap()` preserving unsorted raw-VLQ child line state across a generated-line offset until the upstream sort entrypoints run.
