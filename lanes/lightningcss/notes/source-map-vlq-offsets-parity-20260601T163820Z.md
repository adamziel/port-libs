# Source Map VLQ Offsets Parity - Pruned Input Gaps

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T163820Z`
Base accepted HEAD: `12bccb35cc1c8548c4fcb41c0696df082e8eabd0`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/printer.rs::Printer::add_mapping` remaps printed locations through an input source map with `find_closest_mapping(loc.line, loc.column - 1)` and returns without emitting an output mapping when the closest input-map row has no original source.
- `parcel_sourcemap 2.1.1` keeps generated columns line-local: `add_vlq_map()` resets the generated column to the configured column offset after `;`, while `MappingLine::offset_columns()` sorts and applies offsets only inside the targeted generated line.

## Behavior Pinned

- Added focused SourceMap coverage for a pruned input-map append where the child map has generated-only rows on child line 0, source-backed rows on child line 1, another generated-only gap, and a later source-backed row.
- The generated-only rows and unused source/name tables are pruned, the later source-backed rows keep their own line-local columns instead of inheriting the first-line generated column offset, empty generated-line gaps are retained by the later source-backed lines, buffer round-trip preserves the map, and the consumed child is drained.
- Added `wordpress-source-map-pruned-input-gaps.php` to pin the same behavior for WordPress editor/block source maps without Node, Rust, or an external source-map library.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php` - passed
- `php -l lanes/lightningcss/examples/wordpress-source-map-pruned-input-gaps.php` - passed
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 1035 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-pruned-input-gaps.php --self-test` - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 8754 assertions, 0 failures`

## Delta

- Focused SourceMap assertions: `1018 -> 1035` (`+17`).
- Lane `phpPass`: `8737 -> 8754`.
- Conservative mapped coverage remains `2398 / 3532`; this deepens the already represented source-map VLQ/input-map pruning cluster.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP `SourceMap` VLQ encoder/decoder, generated-offset append path, buffer snapshot support, and WordPress source-map smoke. Full upstream Rust, Node, and WASM runners remain out of scope for this isolated micro-slice.

## Non-Overlap

This does not repeat accepted raw VLQ import/remap, duplicate-column offset boundaries, empty-line add_sourcemap replacement, generated-only direct add_sourcemap preservation, all-skipped table preservation, data URL parsing, CSS Modules, CSSOM, custom-at-rule, media-query, target-prefixing, or bundle/import graph slices. It is limited to pruned generated-only input-map gaps and later source-backed VLQ line-local offset parity.
