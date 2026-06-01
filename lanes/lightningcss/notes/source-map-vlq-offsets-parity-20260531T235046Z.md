# Source Map Empty Span Drain Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T235046Z`
Base: `b2a0ea9050b31220cefa69c10914986b6a41bc76`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` from the pinned `Cargo.lock`.
- Source-truth files:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::offset_lines()`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs::write_vlq()`
- Upstream `offset_lines()` inserts empty `MappingLine` entries with `splice()` for positive offsets and removes a line range with `drain()` for negative offsets. If the drained range removes the only mapped line but leaves other empty line entries, `write_vlq()` still emits semicolons for those remaining generated-line spans.

## Native PHP Delta

- `SourceMapTest.php` now pins the empty-span drain case: a mapping on generated line 2 is padded to `;;AAEAA;;`, then a negative line offset drains that mapping line and preserves the empty generated-line span as `;;;`.
- The same test verifies that mappings are empty, source/name/sourceContent tables remain available, closest lookup on the emptied line returns `null`, buffer round-trip preserves the span, and a later negative line offset can shrink the remaining empty span to `;;`.
- `wordpress-source-map-vlq-offsets.php` now self-tests the same behavior for block/theme generated CSS source maps.

## Verification

- Baseline before this patch: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 363 assertions, 0 failures.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 374 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 4976 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SourceMap assertions move from 363 to 374.
- Full LightningCSS PHP evidence moves from 4965 to 4976 assertions / 0 failures.
- Conservative mapped coverage remains 2212 / 3532 because this deepens the already represented `parcel_sourcemap::SourceMap::offset_lines` line-span offset cluster instead of claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local native Source Map v3/Base64 VLQ implementation, buffer snapshot support, and offset-line bookkeeping with no Node, Rust, browser service, external source-map package, or live-service dependency.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice. This patch does not repeat accepted raw Source Map v3 generated-only/name imports, byte-stream no-comma parsing, positive or negative raw-map line/column offsets, all-skipped raw-VLQ table preservation, duplicate generated-column offset/search behavior, unsorted raw generated-column sorting, empty-line column no-ops/overflow guards, shifted-column overflow guards, past-EOF line spans, `addSourceMap()` replacement/consumption, input-map extension, project-root normalization, JSON/data URL defaults, buffer round trips, bundler SourceMap collection, CSS Modules, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule work. It is limited to preserving generated empty-line spans after `offset_lines()` drains the only mapping line.
