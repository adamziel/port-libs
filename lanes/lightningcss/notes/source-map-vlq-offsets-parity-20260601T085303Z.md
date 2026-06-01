# Source Map Rejected Child Merge Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T085303Z`

Base accepted HEAD: `6c5f68290192c5bf57e0f3c2cca80b604bf38511`

## Source Truth

- Pinned LightningCSS manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1`.
- Local source-truth file: `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`.
- Upstream `SourceMap::add_sourcemap()` uses `std::mem::take` on child sources, names, sources_content, and mapping_lines before remapping. If remapping later rejects an invalid source/name index, the parent has already imported tables and the child is consumed.

## Native PHP Delta

- `SourceMap::addSourceMap()` now drains the child source map in a `finally` block so rejected offset remaps match upstream child-consumption semantics.
- Added focused `SourceMapTest.php` coverage for a malformed child map merged with a positive line offset: parent imported source/name/sourceContent tables remain, parent mappings are unchanged, and child tables/mappings are drained after the exception.
- Added `wordpress-source-map-rejected-child-merge.php --self-test` to model a corrupted generated block stylesheet map without Node/Rust.

## Verification

- Baseline before the new test: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 723 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/SourceMap.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-rejected-child-merge.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 730 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-rejected-child-merge.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7005 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.
- Root harness: not run - isolated micro-slice.

## Coverage Delta

Focused SourceMap assertions moved from `723` to `730`. Full LightningCSS lane assertions moved from `6998` to `7005`. Conservative mapped coverage remains `2360 / 3532` because this deepens the already represented `parcel_sourcemap::SourceMap::add_sourcemap` cluster.

## Dependency Closure

No new support component is needed. This reuses the native PHP Source Map v3/Base64 VLQ implementation and adds no Node, Rust runtime, WASM, browser service, external source-map package, or live-service dependency.

## Non-Overlap

No current LightningCSS rework note existed for this lane. This does not repeat accepted raw VLQ imports, line/column offsets, empty span replacement, negative/positive child offset spans, duplicate generated-column sorting, raw index guards, or normal child consumption. This slice is limited to rejected `add_sourcemap` offset remaps after table import.
