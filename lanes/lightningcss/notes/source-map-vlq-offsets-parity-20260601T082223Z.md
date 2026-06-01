# Source Map VLQ Trailing Empty Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T082223Z`

## Source Truth

- Pinned LightningCSS manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map behavior is delegated through the pinned `parcel_sourcemap 2.1.1` dependency.
- Local upstream source checked:
  `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
- Relevant upstream methods: `SourceMap::offset_lines()` and `SourceMap::add_sourcemap()`.

`offset_lines()` inserts empty mapping-line records after the moved mappings.
`add_sourcemap()` imports child source/name/sourceContent tables before moving
child lines into the parent, skips negative destination lines, replaces each
surviving destination line with the child line, and consumes the child map.

This slice pins the edge where a child source-backed mapping is skipped by a
negative line offset, but trailing empty child lines created by `offset_lines()`
still survive and clear parent lines.

## Changes

- Added a focused `SourceMapTest.php` case for negative `addSourceMap()` offsets
  with trailing empty child spans after the skipped child mapping.
- Added `examples/wordpress-source-map-trailing-empty-offset.php` with a
  WordPress theme/block source-map smoke for the same parity edge.
- Updated lane status and manifest evidence. Conservative mapped coverage stays
  `2360 / 3532` because this deepens the already represented source-map VLQ
  offset cluster.

## Verification

- Baseline before the new test: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  passed `1 test files, 691 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-source-map-trailing-empty-offset.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` passed
  `1 test files, 705 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-trailing-empty-offset.php --self-test`
  passed with `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed
  `13 test files, 6908 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.

## Dependency Closure

No new support component is needed. The slice uses the existing native PHP
`SourceMap` implementation and verifies its parity against the pinned
`parcel_sourcemap` behavior.

## Non-Overlap

No LightningCSS rework note existed for this lane before editing. This does not
repeat the earlier source-map slices for column-drained children, positive
leading empty child spans, all-skipped table preservation, or negative leading
empty child spans before kept mappings. The bounded edge here is trailing empty
child spans that survive after the source-backed child mapping is skipped by a
negative line offset.
