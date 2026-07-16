# Source Map Separator-Only VLQ Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T025558Z`
Base accepted HEAD: `515fa94ece8af5512b4751f4654c8d7fe66ba5ec`

## Source Truth

- Pinned LightningCSS upstream: `parcel-bundler/lightningcss` at
  `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map behavior to `parcel_sourcemap` `2.1.1`,
  pinned in `Cargo.lock`.
- Relevant upstream files inspected locally from the cached crate:
  - `parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::add_vlq_map()`
  - `parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::add_sourcemap()`
  - `parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `parcel_sourcemap-2.1.1/src/vlq_utils.rs`

Upstream imports source, name, and `sourcesContent` tables before decoding raw
VLQ mappings. Mapping separators such as semicolons and commas only advance the
decoder's local/generated state; they do not add mapping lines or generated
segments. When a separator-only child map is merged with `add_sourcemap()`,
upstream still drains and imports the child's tables, but it does not create or
replace generated mapping spans in the parent.

## Native PHP Delta

- `SourceMapTest.php` now covers a raw VLQ string containing only separators
  (`;;;,,`) with non-zero line/column offsets. The test proves the imported
  source/name/sourceContent tables are retained even though no mappings are
  produced.
- The same test merges that table-only child map into a parent map and verifies
  the parent generated VLQ remains unchanged while the child tables are drained.
- `wordpress-source-map-vlq-offsets.php` adds the same build-free WordPress
  theme asset smoke path for a separator-only raw VLQ child map.

## Verification

- Baseline focused run before edits:
  - `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 474 assertions, 0 failures`
- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/SourceMapTest.php`
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 487 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5682 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - Passed.

Focused SourceMap assertions moved from `474` to `487`. Full LightningCSS lane
assertions moved from `5669` to `5682`. Conservative mapped coverage remains
`2314 / 3532` because this deepens the already represented
`parcel_sourcemap::SourceMap::add_vlq_map` raw VLQ import cluster.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the lane-local native
Source Map v3 and Base64 VLQ implementation with no Node, Rust runtime, WASM,
external source-map package, browser service, or live-service credential.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated to this source-map slice. This does not repeat accepted raw Source Map
v3 imports, byte-stream no-comma parsing, generated-only segments, positive or
negative raw-map line/column offsets, fully skipped mapping table preservation,
invalid index guards, duplicate generated-column offsets, unsorted mapping
sorting, empty-span `offsetColumns()` behavior, `addSourceMap()` line
replacement, `extendWithSourceMap()` input remapping, project-root
normalization, JSON/data URL/buffer imports, CSS Modules, CSSOM, media-query,
target-prefixing, bundle/import, or custom at-rule work. It is limited to the
separator-only raw VLQ import path where tables are imported without generated
spans.
