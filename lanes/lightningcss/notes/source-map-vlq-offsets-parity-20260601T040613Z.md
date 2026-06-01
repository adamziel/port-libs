# Source Map VLQ Offsets Parity - 2026-06-01

## Slice

Micro-slice `lightningcss-source-map-vlq-offsets-parity-20260601T040613Z` deepens the already represented LightningCSS SourceMap cluster with parcel_sourcemap behavior for a buffered child map whose only generated mapping line is drained by `offsetColumns()` and then merged into a parent map through a negative `addSourceMap()` generated-line offset.

## Source Truth

- Pinned LightningCSS manifest commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map implementation dependency: `parcel_sourcemap 2.1.1`.
- Upstream PHP parity target inspected locally:
  - `parcel_sourcemap-2.1.1/src/lib.rs`
  - `parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `parcel_sourcemap-2.1.1/src/vlq_utils.rs`

Observed source-truth behavior: `MappingLine::offset_columns()` may drain all mappings while preserving an empty generated line; `SourceMap::to_buffer()` / `from_buffer()` preserve empty mapping lines; `SourceMap::add_sourcemap()` imports source/name/sourceContent tables, skips negative child target lines, replaces non-negative parent generated lines even when the child line is empty, and consumes the child map.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for `mappings: ";;"` child buffer round-trip followed by negative-offset merge into a parent map.
- Added WordPress source-map example self-test coverage for the same behavior using theme-style source paths and names.
- Conservative mapped coverage remains `2336 / 3532`; this slice deepens an already represented parcel_sourcemap SourceMap offset cluster.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` => `1 test files, 545 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 5930 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test` => `OK`

Final lint and diff checks are recorded in the handoff response.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local PHP `SourceMap` implementation and the existing WordPress source-map example harness.

## Non-Overlap

This does not repeat the accepted escaped `@import` supports graph, HWB color-mix hue interpolation, source-map leading empty offset span, separator-only raw VLQ import, negative generated-line guard, child-consumption, or empty child-line merge slices. The added case specifically combines buffered empty-line preservation, column-drain behavior, and negative `addSourceMap()` offset merging.
