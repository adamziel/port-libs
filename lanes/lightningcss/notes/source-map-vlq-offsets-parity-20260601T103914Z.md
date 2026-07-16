# Source Map Same-Line Rejected Child Merge Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T103914Z`
Base accepted HEAD: `25bfd8b5291a9dba8331a5a3b17363ea2ce51f4a`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1`.
- Local source-truth files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

Upstream `SourceMap::add_sourcemap()` takes child source/name/sourceContent
tables up front, then remaps one child `MappingLine` completely before assigning
it to the target generated line. If a later mapping on that same child line
references an invalid name index, earlier child lines already assigned to the
parent remain, the rejecting line is not partially assigned, imported tables
remain in the parent, and the child source map is consumed.

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for a child source map merged at a
  positive generated-line offset where child line 0 is valid and child line 1
  has two mappings, the second with a corrupted name index.
- The pinned parent map output is `AAAAA;ICUCI;ADRDF;AACAC`: parent line 1 is
  replaced by the valid child line, parent line 2 remains the original parent
  mapping because the rejecting child line is all-or-nothing, and parent line 3
  remains intact.
- Extended `wordpress-source-map-rejected-child-merge.php --self-test` with the
  same block-theme source-map path for corrupted generated child maps.

## Verification

- Baseline before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 799 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` ->
  no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-rejected-child-merge.php` ->
  no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 815 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-rejected-child-merge.php --self-test` ->
  `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` ->
  `13 test files, 7418 assertions, 0 failures`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP Source Map
v3/Base64 VLQ implementation and adds no Node, Rust runtime, WASM, browser
service, external source-map package, or live-service dependency.

## Non-Overlap

No current LightningCSS rework note existed for this lane. This slice does not
repeat accepted raw VLQ import, byte-stream no-comma parsing, duplicate or
unsorted generated-column offsets, empty child span replacement, generated-only
child gap preservation, all-skipped table preservation, skipped invalid-index
guards, complete rejected child merge behavior, or partial rejection after a
later child line. It is limited to the same-line `add_sourcemap` remap
atomicity when a later mapping on the target child line rejects after earlier
lines were already assigned.
