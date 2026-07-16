# LightningCSS Source Map VLQ Offsets Parity - 2026-06-01 22:44Z

Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T224444Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1`.
- Source files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
- Local Rust probe against that crate returned `Ok(())` for `offset_columns(1, 3, -4)` and `offset_columns(2, 0, -1)` on empty generated-line spans created by `offset_lines(1, 2)`, preserving `AAAA;;`. The same probe returned an error for `offset_columns(1, u32::MAX, 1)`.

## Native Delta

- `SourceMap::offsetColumns()` now treats negative column offsets on existing empty generated-line spans as upstream no-ops instead of validating `generated_column + offset` before discovering there are no mappings to drain or shift.
- Positive column overflow on an existing empty generated-line span remains guarded.
- `SourceMapTest.php` now pins the live-map and buffer-restored empty-line no-op behavior.
- `wordpress-source-map-vlq-offsets.php --self-test` now covers the same block-theme source-map path.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 1100 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test` - `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 9102 assertions, 0 failures`.
- Changed PHP lint passed for `SourceMap.php`, `SourceMapTest.php`, and `wordpress-source-map-vlq-offsets.php`.
- `git diff --check -- lanes/lightningcss` - passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SourceMap assertions move `1098 -> 1100` (`+2`).
- Full LightningCSS lane assertions move `9100 -> 9102` (`+2`).
- Conservative mapped coverage remains `2439 / 3532`; this deepens the already represented SourceMap VLQ offset cluster.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP Source Map v3/Base64 VLQ implementation, buffer snapshot support, and example harness. No Node, Rust runtime dependency, WASM, browser service, external source-map package, live service, or credential-bearing input was introduced.

## Non-Overlap

This corrects the empty generated-line negative column-offset boundary using direct upstream crate evidence. It does not repeat accepted raw VLQ import/remapping, skipped generated-line relative state, generated-only segments, duplicate generated-column offset/search behavior, unsorted line sorting, empty generated-line span serialization, positive empty child append, `add_sourcemap` replacement/consumption, input-map extension, project-root normalization, data URL parsing, buffer snapshot basics, CSS Modules, CSSOM, media-query, target-prefixing, bundle/import graph, property-value, or custom at-rule work.
