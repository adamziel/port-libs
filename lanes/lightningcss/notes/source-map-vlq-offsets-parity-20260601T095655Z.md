# Source Map Partial Rejected Child Merge Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T095655Z`
Base accepted HEAD: `c6000a6885bc6b5b6b4980e335c606d935a6fb65`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap 2.1.1` for Source Map v3/VLQ mutation.
- Local source-truth files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

`SourceMap::add_sourcemap()` imports child tables with `std::mem::take`, then
remaps and assigns each surviving child generated line into the parent as it is
processed. If a later child line rejects an invalid source/name index, earlier
target lines remain replaced, the failing target line is not assigned, imported
tables stay in the parent, and the child map is consumed.

## Native PHP Delta

- Changed `SourceMap::addSourceMap()` to remap child lines incrementally instead
  of staging all remapped lines before replacing parent lines.
- Preserved existing empty-line replacement semantics by replacing each
  surviving child line, including empty generated spans.
- Added focused `SourceMapTest.php` coverage where child line 0 replaces parent
  line 1, child line 1 rejects with an invalid source index, and parent line 2
  remains intact. The pinned output is:

```text
AAAAA;ICUCG;ADRDD
```

- Extended `wordpress-source-map-rejected-child-merge.php --self-test` with the
  same partial merge rejection path for block/theme generated CSS source maps.

## Verification

- Baseline before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 783 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` ->
  `1 test files, 799 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-rejected-child-merge.php --self-test`
  -> `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` ->
  `13 test files, 7309 assertions, 0 failures`

Additional final lint/diff checks are recorded in the handoff response.
Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP Source Map
v3/Base64 VLQ implementation and adds no Node, Rust runtime, WASM, browser
service, external source-map package, or live-service dependency.

## Non-Overlap

No current LightningCSS rework note existed for this lane. This slice does not
repeat accepted raw VLQ import, byte-stream no-comma parsing, duplicate or
unsorted generated-column offsets, empty child span replacement, generated-only
child gap preservation, all-skipped table preservation, skipped invalid-index
guards, complete rejected child merge behavior, CSS Modules, CSSOM, media query,
target prefixing, bundle/import graph, property-value, or custom at-rule work.
It is limited to partial parent mutation when an upstream `add_sourcemap` merge
rejects after earlier child lines were already applied.
