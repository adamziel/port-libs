# Source Map Column-Drain Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T031243Z`
Base accepted HEAD: `979af834e747cf8f00cd2e2b7b981cbc1e549c29`

## Source Truth

- Pinned LightningCSS upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map handling to `parcel_sourcemap` `2.1.1`, pinned in `Cargo.lock`.
- Relevant upstream files inspected locally:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs::MappingLine::offset_columns()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::write_vlq()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::to_buffer()` and `from_buffer()`

`MappingLine::offset_columns()` drains mappings in the removed generated-column
range and leaves the mapping line itself in the source map. A line that becomes
empty still serializes as a generated-line span when `write_vlq()` walks the
stored mapping lines, and buffer round trips preserve that empty span.

Focused upstream probe against the pinned crate:

```text
single mapping line 2, column 0; offset_columns(2, 1, -1)
write_vlq: ;;
get_mappings: []
buffer round-trip write_vlq: ;;

two mappings line 2, columns 0 and 10; offset_columns(2, 5, -5)
write_vlq: ;;KACAC
surviving mapping: line 2, column 5, original line 1, name 1
```

## Native PHP Delta

- `SourceMapTest.php` now pins column-offset drain behavior for:
  - full drain of the only mapping on a generated line, preserving `;;`, source/name/sourceContent tables, null closest lookup, and buffer round trip;
  - prefix drain where an earlier mapping is removed and a later mapping shifts into the deleted range.
- `wordpress-source-map-vlq-offsets.php` self-tests the same behavior for block/theme source maps emitted without Node, Rust, or WASM at runtime.
- `lane-status.json` records the focused and full-lane assertion deltas.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 500 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5746 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - passed

Focused SourceMap assertions moved from `487` to `500`. Full LightningCSS lane
assertions moved from `5733` to `5746`. Conservative mapped coverage remains
`2320 / 3532` because this deepens the already represented
`parcel_sourcemap::MappingLine::offset_columns` source-map offset cluster.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the lane-local Source Map
v3 and Base64 VLQ implementation and adds no Node, Rust runtime, WASM, external
source-map package, browser service, or live-service dependency.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated to this source-map slice. This does not repeat accepted raw Source Map
v3 imports, byte-stream no-comma parsing, positive/negative raw-map line and
column offsets, skipped-line relative state, invalid index guards, duplicate
generated-column offsets, unsorted mapping sorting, empty-span `offsetLines()`,
separator-only imports, `addSourceMap()` table-drain behavior,
`extendWithSourceMap()` input remapping, project-root normalization, JSON/data
URL/buffer imports, CSS Modules, CSSOM, media-query, target-prefixing,
bundle/import, or custom at-rule work. It is limited to empty generated-line
span preservation after `offset_columns()` drains mappings from a mapping line.
