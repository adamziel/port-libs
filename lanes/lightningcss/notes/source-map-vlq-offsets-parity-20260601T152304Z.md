# Source Map VLQ Offsets Parity - 2026-06-01T15:23:04Z

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T152304Z`
Base accepted HEAD: `1ae10d3b407a43d8a283421317a85a7a1d500366`

## Upstream Source Truth

- Pinned LightningCSS upstream is `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map behavior is provided by `parcel_sourcemap` 2.1.1 in the local Rust crate cache.
- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs:365` drains child source, name, source-content, and mapping-line tables in `add_sourcemap`, remaps child indexes, and replaces generated lines after applying the line offset.
- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs:517` initializes generated line and column offsets for raw VLQ import, then resets `generated_column` to the configured column offset after each `;` line separator. This keeps generated-column deltas line-local instead of carrying the previous line's last column.

## Behavior Added

- Added SourceMap coverage for generated-offset child maps with multiple child generated lines.
- The first appended child line receives the generated column offset, later child lines keep their own VLQ line-local generated columns, generated-only child mappings survive the append, trailing empty child spans are retained, and the consumed child map is drained.
- Updated the WordPress block-source-map smoke to cover the same multi-line generated-offset shape for bundled block CSS source maps.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/SourceMapTest.php`
- `php -l lanes/lightningcss/examples/wordpress-source-map-generated-offset-spans.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-source-map-generated-offset-spans.php`
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 981 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-generated-offset-spans.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8415 assertions, 0 failures`

## Delta

- Focused SourceMap assertions: `965 -> 981` (`+16`).
- Lane `phpPass`: `8399 -> 8415`.
- Mapped upstream denominator remains `2393 / 3532`; this slice deepens an existing source-map mapped cluster rather than admitting a new manifest unit.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `SourceMap` implementation, VLQ encoder/decoder, generated-offset append path, and lane-local WordPress smoke.
