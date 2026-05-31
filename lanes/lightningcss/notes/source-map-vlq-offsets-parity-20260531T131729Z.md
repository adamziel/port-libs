# Source Map VLQ Offset Helpers Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T131729Z`
Base: `27153c38e7cef55880aa33fb66fba5f5470c1f89`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1, used by LightningCSS source-map generation/remapping.
- Targeted upstream helper behavior:
  - `SourceMap::offset_columns` / `MappingLine::offset_columns`: shift generated columns at and after a start column; negative shifts remove overlapped mappings in the destination range.
  - `SourceMap::offset_lines`: insert generated lines for positive offsets and remove shifted-away generated-line ranges for negative offsets.
  - `SourceMap::add_empty_map`: add source-backed line mappings at column 0 with a generated line offset.

## Native PHP Delta

- `SourceMap::offsetColumns()` implements the upstream column-shift and overlap-removal semantics.
- `SourceMap::offsetLines()` implements generated-line insertion/removal by shifting stored mappings.
- `SourceMap::addEmptyMap()` emits source-backed mappings for each source line with line-offset handling.
- `wordpress-source-map-vlq-offsets.php` now uses `offsetColumns()` and `offsetLines()` for preserved license-comment/layer offsets and self-tests an empty generated theme-json CSS map.

## Verification

- Red-first focused test before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` failed on missing `offsetColumns`, `offsetLines`, and `addEmptyMap`.
- `php -l lanes/lightningcss/src/SourceMap.php && php -l lanes/lightningcss/tests/SourceMapTest.php && php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 63 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 1399 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- PHP pass evidence moves from 1382 to 1399 assertions.
- Conservative mapped coverage moves from 1046 to 1049 of 3532 for the three parcel_sourcemap-compatible offset-helper behaviors.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses and extends the lane-local native Source Map v3/VLQ support with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ import/remapping, generated-only segment, closest lookup, invalid index guard, CSSOM transition, or CSS Modules nested-composes work from accepted source `428f6525`. It specifically adds the remaining source-map offset mutator helpers used around generated code movement.
