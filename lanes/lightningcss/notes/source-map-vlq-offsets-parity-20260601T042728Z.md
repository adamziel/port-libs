# Source Map Line-Splice VLQ Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T042728Z`
Base accepted HEAD: `a514b852099d3beeb2c984bc19ea1aeae13dfd49`

## Source Truth

- Pinned LightningCSS upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map offset behavior to `parcel_sourcemap` `2.1.1`, pinned in the upstream `Cargo.lock`.
- Local upstream files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

`SourceMap::offset_lines()` splices whole `MappingLine` entries. For a negative
line offset, the line range before the requested line is drained, later mapping
lines shift down, and a shifted unsorted raw-VLQ line remains unsorted until
`write_vlq()` or closest lookup sorts it.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for negative line-offset splicing
  that removes a preceding mapping line and shifts an unsorted raw-VLQ line
  down before sorting.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same
  WordPress block/theme source-map path, preserving source/name/sourceContent
  tables and closest-mapping behavior.
- Updated lane status and manifest evidence. Conservative mapped coverage
  remains `2336 / 3532` because this deepens the already represented
  `parcel_sourcemap` offset/source-map cluster.

## Verification

- Before focused test: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 527 assertions, 0 failures`.
- After focused test: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 541 assertions, 0 failures`.
- Full focused lane: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 5959 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test` -> `OK`.
- PHP lint: `php -l lanes/lightningcss/tests/SourceMapTest.php` and `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php` -> no syntax errors.
- JSON validation: `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json` and `lanes/lightningcss/lane-status.json` decode with `JSON_THROW_ON_ERROR`.
- Whitespace: `git diff --check -- lanes/lightningcss` -> clean.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP Source
Map v3 and Base64 VLQ implementation; no Node, Rust runtime, WASM, external
source-map package, browser service, or live-service dependency is introduced.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated to this source-map slice. This does not repeat accepted separator-only
raw VLQ imports, skipped invalid-index guards, duplicate generated-column
offsets, nested unsorted maps, raw byte-stream parsing, raw map line/column
offsets, direct unsorted-line movement, column-drained empty spans, leading
empty child offset spans, table-preserving skipped child maps, project-root
normalization, JSON/data URL/buffer imports, CSS Modules, CSSOM, media-query,
target-prefixing, bundle/import, or custom at-rule work. It is limited to
negative `offset_lines()` splicing when a removed preceding line is followed by
an unsorted raw-VLQ mapping line.
