# Source Map Skipped Generated-Only VLQ Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T050220Z`
Base accepted HEAD: `9d2966b89133306c89e1d8c9ef9d120cd603e55f`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` from the pinned `Cargo.lock`.
- Source-truth files inspected locally:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs::read_relative_vlq()`

`add_vlq_map()` advances relative source, original, and name state while
decoding a segment, even when a negative generated-line offset later skips that
segment. A semicolon resets the generated-column accumulator back to the import
column offset, so a later kept source-backed segment is encoded from the state
established by the skipped line.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for raw VLQ input
  `AAAAA,E;ACECC` imported with line offset `-1` and column offset `5`.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same
  block/theme source-map path so the kept mapping lands at generated column 5
  with source/name index 1 while preserving skipped source/name tables.
- The native `SourceMap::addVlqMap()` implementation already matched this
  upstream behavior; this slice locks it with regression and example evidence.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 586 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6120 assertions, 0 failures`

Final PHP lint, JSON validation, and `git diff --check -- lanes/lightningcss`
are recorded in the handoff response.

## Status

- Focused SourceMap assertions move from `576` to `586`.
- Full LightningCSS PHP evidence moves from `6110` to `6120` assertions with
  `0` failures.
- Conservative mapped coverage remains `2348 / 3532`; this deepens the already
  represented `parcel_sourcemap::SourceMap::add_vlq_map` raw VLQ offset
  cluster.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native Source Map
v3/Base64 VLQ implementation and adds no Node, Rust runtime, WASM, browser
service, external source-map package, or live-service dependency.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated to this source-map slice. This patch does not repeat accepted raw
Source Map v3 import, byte-stream no-comma parsing, positive or negative
raw-map line/column offsets, all-skipped table preservation, skipped-line
invalid index guards, duplicate generated-column handling, unsorted line
sorting, empty-span behavior, child-map consumption, `extendWithSourceMap()`,
project-root normalization, JSON/data URL/buffer round-tripping, CSS Modules,
CSSOM, media-query, target-prefixing, bundle/import graph, property-value, or
custom-at-rule work. It is limited to skipped generated-only VLQ state before a
later kept source-backed mapping under negative line-offset import.
