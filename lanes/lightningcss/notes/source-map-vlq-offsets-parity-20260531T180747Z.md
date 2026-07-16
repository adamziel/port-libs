# Source Map Duplicate-Column Offset Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T180747Z`
Base: `a43e5199edaebf1f3618a8886cdb4a32d7a17171`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream files:
  - `parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()`, which permits adjacent Base64 VLQ mappings without comma separators.
  - `parcel_sourcemap-2.1.1/src/mapping_line.rs::offset_columns()`, which uses Rust `binary_search_by()` boundaries for duplicate generated columns.
- Local upstream probe with `parcel_sourcemap` 2.1.1 confirmed:
  - Raw `AAAAAA,C`, then `offset_columns(0, 0, 5)`, serializes as `AAAAA,K,C`.
  - Raw `AAAAAA,K`, then `offset_columns(0, 5, -5)`, serializes as `AAAAA,A`.

## Native PHP Delta

- `SourceMap::offsetColumns()` now uses a Rust-compatible binary-search boundary helper instead of lower-bound semantics.
- Duplicate generated-column mappings produced by permissive raw VLQ byte-stream imports now shift or drain the same duplicate subset as upstream.
- `SourceMapTest.php` adds focused positive and negative offset cases for duplicate generated columns.
- `wordpress-source-map-vlq-offsets.php` self-tests the same path for generated block/theme source-map diagnostics.

## Verification

- Red-first probe before implementation:
  - PHP serialized the positive case as `KAAAA,A,C` instead of upstream `AAAAA,K,C`.
  - PHP serialized the negative case as `A` instead of upstream `AAAAA,A`.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 187 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2889 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 179 to 187.
- Full LightningCSS PHP evidence moves from 2881 to 2889 pass / 0 fail.
- Conservative mapped coverage moves from 1637 to 1638 of 3532 for the additional `parcel_sourcemap::MappingLine::offset_columns` duplicate-boundary behavior.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 generated-only/name imports, byte-stream VLQ import itself, negative raw-VLQ line-offset import, relative VLQ guard failures, basic `offsetColumns()`/`offsetLines()`/`addEmptyMap()` behavior, empty generated-line spans, `addSourceMap()` line replacement, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, public offset overflow guards, bundle SourceMap source collection, CSS Modules, CSSOM, media-query, target-prefixing, or custom-at-rule work. It is limited to duplicate generated-column offset semantics after raw byte-stream VLQ import.
