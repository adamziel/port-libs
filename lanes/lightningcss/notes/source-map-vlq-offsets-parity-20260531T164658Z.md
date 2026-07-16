# Source Map Empty-Line Offset Guard Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T164658Z`
Base: `3cb6c54742fb94be753dab5a9b5666c54f8c61b5`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior:
  - `SourceMap::offset_columns()` no-ops when the generated line is missing.
  - `MappingLine::offset_columns()` still validates `generated_column + generated_column_offset` before inspecting mappings, so an existing empty `MappingLine` rejects a negative destination column.
  - Existing empty generated-line spans are created by `SourceMap::offset_lines()` and serialized as trailing `;` in VLQ output.

## Native PHP Delta

- `SourceMap::offsetColumns()` now distinguishes a missing generated line from an existing empty generated-line span.
- Positive column offsets on empty generated-line spans remain no-ops.
- Negative offsets that would move an existing empty generated-line span before column zero now throw `InvalidArgumentException`, matching upstream guard behavior.
- `wordpress-source-map-vlq-offsets.php` now self-tests the empty generated-line guard used by generated WordPress/theme CSS maps.

## Verification

- Red-first focused test before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` failed with `Expected exception InvalidArgumentException was not thrown`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 116 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2316 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.

## Status

- Focused SourceMap assertions move from 113 to 116.
- Conservative mapped coverage moves from 1446 to 1447 of 3532 for the additional `parcel_sourcemap` empty generated-line offset guard.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/VLQ implementation with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ import/remapping, generated-only segments, line/column offset import, `addSourceMap()` line replacement, empty generated-line span preservation, input-map `extends`, JSON/data URL import defaults, project-root source normalization, CSS Modules, CSSOM, bundler, media-query, or target-prefixing work. It is limited to the remaining `offset_columns` validation edge for already-existing empty generated-line spans.
