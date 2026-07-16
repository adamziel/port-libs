# Source Map Raw VLQ Table-Deduped Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T023735Z`
Base accepted HEAD: `d66a5b3de6df2dc65a32a2f70e37d0a3eee8d74f`

## Source Truth

Pinned LightningCSS upstream is `parcel-bundler/lightningcss` at
`22bdda3d190f1cd321d98026225cfc964af64ad9`. LightningCSS delegates source-map
handling to `parcel_sourcemap` 2.1.1.

The upstream parity point for this slice is `parcel_sourcemap/src/lib.rs`:
`SourceMap::add_vlq_map()` calls `add_sources()` and `add_names()` before
decoding raw VLQ mappings, then remaps `sourcesContent` through the deduplicated
source index. `add_source()` and `add_name()` deduplicate existing table entries,
and duplicate imported `sourcesContent` values overwrite the content on the
remapped source index. Generated line/column offsets are then applied while
decoding mappings.

## Native PHP Delta

The native `SourceMap` implementation already had the upstream-compatible
dedupe/remap behavior. This patch adds focused regression coverage around the
combined case: pre-existing source/name entries, imported duplicate source/name
entries, duplicate `sourcesContent`, and generated line/column offsets in a raw
VLQ import.

The WordPress source-map example now includes the same scenario for a theme
asset path, proving the build-free source-map output remains stable without
Node, Rust, or WASM at runtime.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php && php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
  - No syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 460 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5533 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss`
  - Passed.

Focused SourceMap assertions moved from 448 to 460. Full LightningCSS lane
assertions moved from 5521 to 5533. Conservative mapped coverage remains
`2303 / 3532` because this deepens the already represented
`parcel_sourcemap::add_vlq_map` raw VLQ import cluster.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the lane's native Source
Map v3 and Base64 VLQ implementation and does not require Node, Rust, WASM, an
external source-map package, or live services.

## Non-Overlap

This does not repeat accepted raw Source Map v3 import, byte-stream no-comma
parsing, negative line/column offsets, skipped-line state, invalid index guards,
duplicate generated columns, unsorted mapping sorting, empty-span
`offsetColumns()`, `addSourceMap()` extension behavior, project-root
normalization, JSON/data URL/buffer imports, or the recent bundle/CSS Modules/
CSSOM/media/target/custom-at-rule supervisor batch. The stale CustomMedia
rework note was inspected and is unrelated to this source-map slice.
