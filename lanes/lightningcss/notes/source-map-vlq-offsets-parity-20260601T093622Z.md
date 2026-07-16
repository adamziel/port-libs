# Source Map VLQ Offsets Parity - 2026-06-01T09:36:22Z

## Slice

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T093622Z`
Accepted base: `aed6a2d8d2909439bc5d1a8c249768044294198e`

This slice deepens the represented SourceMap offset/VLQ cluster with the
parcel_sourcemap behavior where `add_sourcemap` replaces a parent line range
with child lines at a positive generated-line offset, including a generated-only
child line sandwiched between two source-backed child mappings.

## Source Truth

- LightningCSS upstream is pinned in `UPSTREAM_TEST_MANIFEST.json` at
  `parcel-bundler/lightningcss` commit
  `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map parity follows the upstream Rust dependency used by LightningCSS:
  `parcel_sourcemap` 2.1.1.
- Local source-truth files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

Upstream behavior used for this slice:

- Child map lines are moved by `line_offset` and replace the parent map span
  from the offset line through the child's last line.
- Generated-only child mapping lines remain mappings after the merge instead of
  being dropped as empty span placeholders.
- VLQ writing does not update previous source/original/name state for
  generated-only segments, so the next source-backed child mapping remains
  delta-encoded from the prior source-backed child mapping.

## Native Coverage

Added a focused SourceMap test that merges a child map with:

- source-backed child line 0: `AAUAA`
- generated-only child line 1: `M`
- source-backed child line 2: `GAEEC`

The parent receives the child at line offset 1 and serializes to:

```text
AAAAA;ACUAM;M;GAEEC;ADRFH;AACAC
```

The test asserts the decoded generated lines, generated columns, source indexes,
original lines/columns, name indexes, source/name tables, closest generated-only
lookup, after-child lookup, round-trip JSON import, and child-map drain.

The WordPress smoke example now includes the same generated-only child gap in a
block theme source-map sandwich so shared-hosting builds can preserve unmapped
generated CSS lines without Node/WASM.

## Verification

- Baseline before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  -> `1 test files, 765 assertions, 0 failures`
- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-source-map-trailing-empty-offset.php`
  -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  -> `1 test files, 783 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-trailing-empty-offset.php --self-test`
  -> `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  -> `13 test files, 7210 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'`
  -> `JSON OK`
- `git diff --check -- lanes/lightningcss`
  -> passed

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`PortLibs\LightningCSS\SourceMap` implementation and does not add Rust, Node,
WASM, browser, or external service dependencies.

## Non-Overlap

This is distinct from accepted source-map work covering generated-only child
lines during negative-offset merge, leading/trailing/interior empty child span
replacement, direct raw-VLQ offset boundaries, duplicate/unsorted generated
columns, source/name/content remapping, invalid index guards, rejected child
merge behavior, and line-offset table preservation. This slice specifically
guards a generated-only child mapping between source-backed child mappings after
a positive `addSourceMap` merge.

## Root Harness

Not run - isolated micro-slice.
