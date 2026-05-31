# Source Map Generated-Only Offset Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T171229Z`
Base: `568c1f2dc06c3f218e0ebf7f60d307c632e8dd1c`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior:
  - `SourceMap::add_mapping_with_offset` accepts a `Mapping` whose `original` field is `None`.
  - Generated-only mappings still receive generated line and column offsets.
  - The resulting VLQ segment contains only the generated-column field, while following source-backed segments keep normal source/original/name deltas.
  - Negative line or column offset results are rejected through the same coordinate guard used by source-backed offset mappings.

## Native PHP Delta

- `SourceMap` now exposes `addGeneratedMappingWithOffset()` for generated-only offset mappings.
- `SourceMapTest.php` adds a focused parity case that serializes generated-only and source-backed offset mappings together as `;;Y,MAAAA;E`.
- `wordpress-source-map-vlq-offsets.php` now self-tests the generated-only offset path for generated theme/editor CSS map spans.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record the focused assertion and conservative mapped-coverage movement.

## Verification

- Red-first focused test before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` failed with `Call to undefined method PortLibs\LightningCSS\SourceMap::addGeneratedMappingWithOffset()`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 147 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2553 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.

## Status

- Focused SourceMap assertions move from 139 to 147.
- Full LightningCSS PHP evidence moves from 2545 to 2553 pass / 0 fail.
- Conservative mapped coverage moves from 1553 to 1554 of 3532 for the generated-only `add_mapping_with_offset` source-map behavior.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/VLQ implementation with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ import/remapping, generated-only raw segments, line/column raw-map import offsets, `offsetColumns()`/`offsetLines()`/`addEmptyMap()`, empty generated-line spans, `addSourceMap()` line replacement, input source-map remapping, project-root source normalization, JSON/data-url defaults, SourceMap overflow guards, or SourceMap lookup/getter coverage. The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this current source-map slice.
