# Source Map Leading Empty Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T034706Z`
Base accepted HEAD: `6a9d70d6e954052f2443a5cdc428898114c4a14e`

## Source Truth

- Pinned LightningCSS upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Local source-truth files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

`SourceMap::offset_lines()` inserts empty `MappingLine` entries for positive offsets. `SourceMap::add_sourcemap()` then replaces parent mapping lines with every child line whose `line + line_offset >= 0`, including those leading empty child lines, imports source/name/sourceContent tables, and drains the child map.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage proving leading empty child offset spans replace parent mappings at the shifted generated lines.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with a WordPress path smoke for the same addSourceMap replacement and child-consumption behavior.
- The existing native `SourceMap` implementation already matched this upstream behavior; this slice pins the parity and guards the VLQ output.

## Verification

- Before focused test: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 500 assertions, 0 failures`.
- After focused test: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 512 assertions, 0 failures`.
- Full focused lane: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 5817 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test` -> `OK`.
- PHP lint: `php -l lanes/lightningcss/tests/SourceMapTest.php` and `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php` -> no syntax errors.
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` decode with `JSON_THROW_ON_ERROR`.
- Whitespace: `git diff --check -- lanes/lightningcss` -> clean.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP `SourceMap` and Base64 VLQ implementation.

## Non-Overlap

This does not repeat accepted separator-only raw VLQ table import, skipped invalid-index guards, duplicate generated-column offsets, nested unsorted maps, raw byte-stream parsing, line/column raw map import, or direct offset-lines EOF preservation. It is limited to `add_sourcemap` replacement semantics when the child map contains leading empty `MappingLine` spans created by `offset_lines()`.
