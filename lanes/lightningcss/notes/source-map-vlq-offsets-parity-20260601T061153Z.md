# Source Map Triple Duplicate VLQ Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T061153Z`

Base accepted HEAD: `e62cd70f8878634e62c625c3c5a18ef1e32398d5`

## Source Truth

Pinned LightningCSS commit `22bdda3d190f1cd321d98026225cfc964af64ad9` delegates source-map mutation to `parcel_sourcemap` 2.1.1. The source-truth check for this slice inspected `parcel_sourcemap/src/mapping_line.rs`, where `MappingLine::offset_columns()` sorts the line and uses Rust `binary_search_by()` to choose the generated-column boundary before applying positive offsets or draining a negative-offset span.

The focused parity edge is a same-line VLQ map with three generated-column duplicates. Current Rust binary-search behavior chooses the final equal boundary for this sequence, so a positive offset at column 0 leaves the first two column-0 entries in place and shifts the third duplicate plus the tail. A negative offset from column 5 back to 0 drains only the pre-boundary span and leaves the shifted source-backed tail as the closest column-0 mapping.

## Native Delta

`SourceMapTest.php` now covers raw VLQ `AAAAAA,A,CACAC` and `AAAAAA,A,KACAC` for:

- exact same-column closest lookup over three duplicate generated columns;
- positive `offsetColumns(0, 0, 5)` boundary behavior;
- negative `offsetColumns(0, 5, -5)` drain and closest-source behavior.

`wordpress-source-map-vlq-offsets.php` exercises the same triple-duplicate positive and negative offset path for block/theme source maps.

## Verification

Local commands completed for this handoff:

- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- JSON parse check for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: OK.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file / 652 assertions / 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files / 6421 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

The full upstream Rust/Node/WASM LightningCSS runners were not executed for this isolated micro-slice.

## Status

This deepens an already represented source-map VLQ offset cluster, so conservative mapped coverage remains `2359 / 3532`. The focused PHP assertion delta is `+18` over the accepted 6403-assertion lane baseline. No new support component is required.
