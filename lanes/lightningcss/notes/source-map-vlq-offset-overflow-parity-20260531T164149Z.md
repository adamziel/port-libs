# Source Map VLQ Offset Overflow Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T164149Z`
Base: `3cb6c54742fb94be753dab5a9b5666c54f8c61b5`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior:
  - `SourceMap::add_mapping_with_offset` rejects generated line or column offsets that exceed `u32::MAX`.
  - `MappingLine::offset_columns` rejects generated column plus offset overflow.
  - `SourceMap::offset_lines` rejects generated line plus offset overflow.
  - `add_vlq_map` rejects raw VLQ generated-column offset arithmetic that exceeds the unsigned 32-bit coordinate range.

## Native PHP Delta

- `SourceMap` now validates non-negative generated/source-map coordinates against an unsigned 32-bit maximum before storing mappings or applying offset arithmetic.
- `SourceMapTest.php` adds a focused overflow-boundary parity case covering generated line offsets, generated column offsets, column shifts, line shifts, and raw VLQ column offsets.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` record the focused PHP assertion and conservative mapped-coverage movement.

## Verification

- Red-first focused test before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` failed with `Expected exception InvalidArgumentException was not thrown`.
- `php -l lanes/lightningcss/src/SourceMap.php && php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 118 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2318 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- PHP pass evidence moves from 2313 to 2318 assertions.
- Conservative mapped coverage moves from 1446 to 1447 of 3532 for the parcel_sourcemap coordinate overflow boundary.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/VLQ support with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted `offsetColumns()`/`offsetLines()`/`addEmptyMap()` basic offset mutators, `addSourceMap()` line replacement, empty-line spans, `extendWithSourceMap()` remapping, project-root normalization, JSON/data URL import/export, CSS Modules, bundler, or CSSOM work. It only covers the missing unsigned 32-bit overflow guards for source-map offset arithmetic.
