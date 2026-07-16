# Source Map VLQ Offsets Parity - 2026-06-01

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T083921Z`
Accepted base: `003a6cf12f2f391f74550d547f03de1f43af9ba7`

## Source Truth

- Upstream LightningCSS manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Behavior source: LightningCSS depends on `parcel_sourcemap 2.1.1`; `SourceMap::offset_lines()` inserts empty mapping lines and `SourceMap::add_sourcemap()` replaces parent target lines for each surviving child line, including empty child lines, while consuming the child map.
- A local Rust oracle using `parcel_sourcemap 2.1.1` confirmed the expected positive-offset VLQ output:
  - child after `offset_lines(2, 2)`: `AAAAA;AACAC;;`
  - merged parent after `add_sourcemap(..., 1)`: `AAAAA;ACAAM;AACAC;;;ADIAF`

## Change

- Added focused PHP coverage for the positive-offset case where a child map has kept mappings followed by trailing empty child spans. The empty spans clear the corresponding parent lines, while later parent lines remain intact.
- Extended the WordPress source-map smoke to exercise the same trailing-empty child-span replacement path for theme/block CSS source maps.
- Updated lane status and manifest evidence to the verified `7001` full-lane assertion count. Conservative mapped coverage remains `2360 / 3532` because this deepens an already represented source-map/VLQ offset cluster.

## Verification

- Baseline before the new focused test: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 723 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-trailing-empty-offset.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 740 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-trailing-empty-offset.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7001 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `SourceMap` implementation and does not add Node, Rust, WASM, network, or live-service runtime dependencies. Full upstream Rust/Node/WASM runners were not executed for this isolated slice.

## Non-Overlap

This slice does not repeat the accepted negative-offset trailing-empty child-span coverage from source `003a6cf12f2f`; it targets the positive-offset replacement case where kept child mappings are followed by empty child lines that clear middle parent lines.
