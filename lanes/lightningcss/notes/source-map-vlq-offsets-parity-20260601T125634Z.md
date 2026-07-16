# Source Map Positive Empty Child Offset Parity - 2026-06-01

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T125634Z`
Base accepted HEAD: `27cf721c25e91c9dcac0b599677df25582e922d2`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS source-map mutation is delegated to `parcel_sourcemap 2.1.1`.
- Inspected local source-truth files:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`

Upstream `SourceMap::add_sourcemap()` iterates every child `mapping_lines`
entry, including empty lines, and assigns each non-negative generated line into
the parent after applying `line_offset`. A child map whose only mapping was
drained by `MappingLine::offset_columns()` still has empty generated-line
spans; merging it at a positive offset extends the parent VLQ with trailing
empty-line semicolons while preserving the child's source/name/sourceContent
tables and consuming the child map.

## Native Delta

- Added focused `SourceMapTest.php` coverage for a column-drained child map
  with VLQ `;;` merged into a parent at line offset `5`.
- Verified parent VLQ `AAAAA;;;;;;;`, empty closest lookup on the inserted
  span, source/name/sourceContent preservation, buffer round-trip behavior, and
  upstream-style child consumption.
- Added `wordpress-source-map-positive-empty-child-offset.php --self-test` as
  a build-free WordPress block/theme source-map smoke for the same behavior.

The existing native PHP `SourceMap` implementation already matched this
upstream behavior. This slice pins the weakly mapped offset edge with focused
assertions and WordPress evidence.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/SourceMapTest.php`
- `php -l lanes/lightningcss/examples/wordpress-source-map-positive-empty-child-offset.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-source-map-positive-empty-child-offset.php`
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 923 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-positive-empty-child-offset.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7941 assertions, 0 failures`

Root harness: not run - isolated micro-slice.

## Coverage Delta

- Focused SourceMap coverage moved from `909` to `923` assertions.
- Full LightningCSS lane coverage moved from `7927` to `7941` assertions.
- `lane-status.json` `phpPass` moved from `7927` to `7941`.
- Conservative mapped coverage remains `2392 / 3532` because this deepens the
  already represented `parcel_sourcemap::SourceMap::add_sourcemap` and VLQ
  offset cluster.

## Non-Overlap

This does not repeat raw VLQ import, duplicate or unsorted generated-column
offsets, negative child offsets, rejected child merge, same-line child merge,
input-map extension, data URL import, project-root normalization, CSS Modules,
CSSOM, bundle/import graph, media-query, property-value, custom-at-rule, or
target-prefix slices. It is limited to positive-offset empty child generated
spans past parent EOF.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP Source Map
v3/Base64 VLQ implementation and existing lane test/example harnesses with no
Node, Rust runtime, WASM, browser API, network access, live credentials, or
external source-map package.
