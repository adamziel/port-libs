# Source Map Empty Child Merge Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T033342Z`
Base accepted HEAD: `86e2d14305df2668712f30216ab52d92b6b533a7`

## Source Truth

- Pinned LightningCSS upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map handling to `parcel_sourcemap` `2.1.1`, pinned in `Cargo.lock`.
- Relevant upstream files inspected locally:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::add_sourcemap()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs::MappingLine::offset_columns()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::write_vlq()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::to_buffer()` and `from_buffer()`

Upstream `add_sourcemap()` moves every child `MappingLine` into the parent,
including empty lines left behind after `offset_columns()` drains all mappings
from that line. Those empty child lines still replace the corresponding parent
generated lines, while the child source/name/sourceContent tables are imported
and the child map is consumed.

## Native PHP Delta

- `SourceMapTest.php` now covers a child source map whose only mapping is
  drained by `offsetColumns(2, 1, -1)`, leaving the child VLQ as `;;`.
- The parent `addSourceMap($child, 1)` path is pinned to replace parent lines
  with the child empty span, preserve the child tables, return null closest
  lookup inside the empty span, round-trip through the native buffer, and drain
  the child map.
- `wordpress-source-map-vlq-offsets.php` adds the same build-free WordPress
  theme asset smoke path.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` record the focused and
  full-lane verification counts. Conservative mapped coverage remains
  `2320 / 3532` because this deepens the already represented
  `parcel_sourcemap` source-map offset cluster.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  - No syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
  - No syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 515 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5808 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - Passed.

Focused SourceMap assertions moved from `500` to `515`. Full LightningCSS lane
assertions moved from `5793` to `5808`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local Source Map v3
and Base64 VLQ implementation and adds no Node, Rust runtime, WASM, external
source-map package, browser service, or live-service dependency.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated to this source-map slice. This patch does not repeat accepted raw
Source Map v3 imports, byte-stream no-comma parsing, positive/negative raw-map
line and column offsets, skipped-line relative state, invalid index guards,
duplicate generated-column offsets, unsorted mapping sorting, empty-span
`offsetLines()` or standalone `offsetColumns()` behavior, separator-only
imports, source/name/content table dedupe, `extendWithSourceMap()` input
remapping, project-root normalization, JSON/data URL/buffer imports, CSS
Modules, CSSOM, media-query, target-prefixing, bundle/import, property-value,
or custom at-rule work. It is limited to `add_sourcemap()` parent-line
replacement when a child source map contains only empty mapping lines after a
column-offset drain.
