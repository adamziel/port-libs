# Source Map VLQ Partial Invalid Index Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T044015Z`
Base accepted HEAD: `afcfa557a3b80f26793d8ccfde38278bad8d53e4`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` from the pinned `Cargo.lock`.
- Source-truth files inspected locally:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs::read_relative_vlq()`

`add_vlq_map()` imports source/name/sourceContent tables before decoding the raw
VLQ byte stream, then calls `add_mapping()` immediately after each valid segment.
If a later same-map segment references an out-of-range source or name, upstream
returns an error after preserving the earlier generated-line/column-offset
mapping and the imported tables.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for partial raw VLQ imports where
  the first offset-adjusted mapping remains after a later source-index or
  name-index validation error.
- Extended `wordpress-source-map-vlq-offsets.php` self-test with the same
  behavior using block/theme source-map paths and names.
- The native `SourceMap::addVlqMap()` implementation already matched upstream;
  this slice locks the behavior with regression tests and WordPress smoke
  evidence.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 562 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6018 assertions, 0 failures`

Final PHP lint, JSON validation, and `git diff --check -- lanes/lightningcss`
are recorded in the handoff response.

## Status

- Focused SourceMap assertions move from `545` to `562` for this current-base
  worker.
- Full LightningCSS PHP evidence is `13 files / 6018 assertions / 0 failures`.
- Conservative mapped coverage remains `2336 / 3532`; this deepens the already
  represented `parcel_sourcemap::SourceMap::add_vlq_map` raw VLQ offset cluster.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native Source Map
v3/Base64 VLQ implementation and adds no Node, Rust, browser service, external
source-map package, or live-service dependency.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated to this source-map slice. This patch does not repeat accepted raw
Source Map v3 import, byte-stream no-comma parsing, generated-only segments,
positive or negative raw-map line/column offsets, all-skipped table
preservation, skipped-line invalid index guards, duplicate generated-column
handling, unsorted line sorting, empty-span behavior, `addSourceMap()`,
`extendWithSourceMap()`, project-root normalization, JSON/data URL/buffer
round-tripping, CSS Modules, CSSOM, media-query, target-prefixing, bundle/import
graph, property-value, or custom-at-rule work. It is limited to partial raw VLQ
import mutation semantics when later index validation fails.
