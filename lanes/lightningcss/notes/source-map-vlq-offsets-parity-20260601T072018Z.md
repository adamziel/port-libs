# Source Map Middle Column-Drain Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T072018Z`
Base accepted HEAD: `80f68770eb80ae23d626c7edafcf276d6f4e32ec`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1`.
- Local source-truth files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`

`MappingLine::offset_columns()` sorts the target line, binary-searches the
requested generated-column boundary, and for negative offsets drains the
mapping window before shifting later mappings. When the window drains every
mapping on a middle generated line, upstream keeps the empty line span in the
VLQ output. `SourceMap::write_vlq()` then encodes later mappings from the last
surviving written source/name/original state, not from the drained mapping.

## Native PHP Delta

- Added `SourceMapTest.php` coverage for a three-line map where line 1 is
  drained by `offsetColumns(1, 1, -1)` while line 2 survives.
- Asserted the exact VLQ string `AAAAA;;IAEEE`, later generated/original/name
  deltas, null closest lookup on the drained middle line, preserved source
  content/name tables, and buffer round-trip behavior.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same
  block/theme middle-line drain path.

The existing native implementation already matched this bounded upstream
behavior; this slice pins it with focused parity coverage and a WordPress
source-map smoke.

## Verification

- Baseline before implementation:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 659 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 668 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` ->
  `13 test files, 6707 assertions, 0 failures`.

Final PHP lint, JSON validation, and `git diff --check -- lanes/lightningcss`
are recorded in the handoff response.

## Status

- Focused SourceMap assertions move from `659` to `668`.
- Full LightningCSS PHP evidence moves from `6698` to `6707` assertions.
- Conservative mapped coverage remains `2360 / 3532`; this deepens the
  already represented `parcel_sourcemap` VLQ column-offset cluster.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP
Source Map v3/Base64 VLQ implementation and adds no Node, Rust runtime, WASM,
browser service, external source-map package, or live-service dependency.

## Non-Overlap

No current LightningCSS rework note existed for this lane. I inspected the two
just-ready source-map handoffs in the main repo queue and avoided their queued
dual-duplicate boundary and unsorted trailing negative-window drain coverage.
This slice does not repeat accepted raw Source Map v3 import, byte-stream
no-comma parsing, generated-only segments, positive or negative raw-map
line/column offsets, all-skipped table preservation, invalid-index guards,
partial invalid-index mutation, duplicate generated-column offsets/search,
unsorted direct write/lookup/line-offset behavior, empty child merge,
trailing negative window drain, `addSourceMap()` consumption,
`extendWithSourceMap()`, project-root normalization, JSON/data URL defaults,
CSS Modules, CSSOM, media-query, target-prefixing, bundle/import graph,
property-value, or custom-at-rule work. It is limited to later VLQ delta state
after a middle generated line is completely drained by a negative column
offset.
