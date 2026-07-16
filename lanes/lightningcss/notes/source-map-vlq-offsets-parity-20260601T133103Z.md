# Source Map Line-Local Offset Sorting Parity - 2026-06-01

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T133103Z`
Base accepted HEAD: `3fbf3e52f7c6e6a72c8a17054cab01a393183925`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1`.
- Local source-truth files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`

Upstream `SourceMap::offset_columns()` fetches only one generated
`MappingLine` and calls that line's `offset_columns()`. That line is sorted,
negative/positive offsets are applied there, and other generated lines remain
in their prior insertion order until a later sort entrypoint such as
`write_vlq()` or `find_closest_mapping()` touches them.

## Native Delta

- Added focused `SourceMapTest.php` coverage for a two-line map with both
  lines initially out of generated-column order.
- The test verifies `offsetColumns(1, 5, 2)` sorts and shifts only line 1:
  read order changes from `[10, 2, 8, 3]` to `[10, 2, 3, 10]`, then `writeVlq()`
  sorts all lines to emit `EACAC,QADAD;GAGAG,OADAD`.
- Added `wordpress-source-map-line-local-offset.php --self-test` to pin the
  same block-theme source-map behavior without Node, Rust, WASM, or a browser.

The existing native PHP implementation already matched upstream behavior. This
slice pins the weakly mapped offset boundary with focused assertions and a
WordPress smoke.

## Verification

- Baseline focused evidence before this slice:
  - `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 923 assertions, 0 failures`
- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-line-local-offset.php`
  - no syntax errors.
- `php lanes/lightningcss/examples/wordpress-source-map-line-local-offset.php --self-test`
  - `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 935 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8030 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss`
  - passed.

Root harness: not run - isolated micro-slice.

## Coverage Delta

- Focused SourceMap coverage moved from `923` to `935` assertions.
- Full LightningCSS lane evidence moved from `8018` to `8030` assertions.
- `lane-status.json` `phpPass` moved from `8018` to `8030`.
- Conservative mapped coverage remains `2392 / 3532` because this deepens the
  already represented `parcel_sourcemap::MappingLine::offset_columns` cluster.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP
Source Map v3/Base64 VLQ implementation and existing test/example harnesses
with no Node, Rust runtime, WASM, browser API, network access, live
credentials, or external source-map package.

## Non-Overlap

No current LightningCSS rework note existed for this lane before editing. This
does not repeat raw VLQ import, duplicate-column positive/negative offset
drains, empty generated-line spans, add_sourcemap child replacement/rejection,
input-map extension, data URL parsing, project-root normalization, CSS Modules,
CSSOM, bundle/import graph, media-query, property-value, custom-at-rule, or
target-prefix slices. It is limited to line-local sorting and shifting at the
`offsetColumns()` entrypoint.
