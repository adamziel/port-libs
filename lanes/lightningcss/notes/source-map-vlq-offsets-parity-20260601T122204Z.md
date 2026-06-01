# Source Map Duplicate Shift-Boundary Offset Parity - 2026-06-01T12:22Z

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T122204Z`
Base accepted HEAD: `104a9f5fce0ab0f0e77688b3f9277242f2f9e31c`

## Source Truth

Pinned upstream is `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
LightningCSS delegates source-map offset mutation to `parcel_sourcemap 2.1.1`.
This slice used:

- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
- `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

`MappingLine::offset_columns()` sorts a mapping line, uses Rust
`binary_search_by()` for both the negative-offset drain start and the shift
start, drains `start_index..index`, then shifts mappings from `index` onward.
With duplicate generated columns exactly at the shift boundary, the upstream
binary search selects the later duplicate, so earlier mappings in the drain
window, including the earlier duplicate at the same generated column, are
removed while the final duplicate shifts.

## Native Delta

- Added `SourceMapTest.php` coverage for raw VLQ mappings with generated
  columns `[0, 4, 5, 5, 10]`.
- Guarded `offsetColumns(0, 5, -3)` parity: generated column `4` and the first
  duplicate at generated column `5` drain, while the second duplicate shifts to
  generated column `2` and the later mapping shifts to `7`.
- Pinned the resulting VLQ output `AAAAA,EAGAG,KACAC`, closest lookup inside
  the drained window, buffer round-trip behavior, preserved sourceContent, and
  preserved name table.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same
  duplicate shift-boundary drain path for block-theme source-map delivery.

The native PHP implementation already followed this upstream behavior through
its Rust-style generated-column binary search and offset application. This
slice pins the weakly mapped edge with focused assertions and WordPress smoke
evidence.

## Verification

Baseline before this slice:

- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 858 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7694 assertions, 0 failures`

After this slice:

- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/SourceMapTest.php`
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 868 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7704 assertions, 0 failures`

Root harness: not run - isolated micro-slice.

## Coverage Delta

Focused SourceMap coverage increased by 10 assertions, from 858 to 868.
Full LightningCSS focused lane evidence increased by 10 assertions, from 7694
to 7704. Conservative mapped coverage remains `2374 / 3532` because this
deepens the already represented SourceMap VLQ offset cluster rather than adding
a new upstream denominator row.

## Non-Overlap

This slice does not repeat raw generated-only import, duplicate-column positive
offsets, duplicate start-column negative offsets, after-last/before-first
lookup fallback, add_sourcemap line replacement, data URL parsing, project-root
normalization, CSS Modules, CSSOM, bundle/import graph, media-query,
property-value, custom-at-rule, or target-prefix work. It is limited to the
negative offset drain window when duplicate generated columns sit at the shift
boundary.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
Source Map v3/Base64 VLQ implementation and does not require Node, Rust, WASM,
browser APIs, network access, live service credentials, or an external package.
