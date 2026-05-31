# Source Map Empty-Line Column Overflow Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T220713Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS uses `parcel_sourcemap` `2.1.1` from pinned `Cargo.lock`.
- Upstream source truth: `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs::offset_columns()` and `src/lib.rs::offset_columns()`.
- Local upstream probe against the pinned crate confirmed:
  - `offset_columns()` on an existing empty generated line rejects `u32::MAX + 1` positive column overflow.
  - `offset_columns()` on an existing empty generated line still no-ops negative underflow.
  - `offset_columns()` on a missing generated line no-ops positive overflow because `SourceMap::offset_columns()` returns before entering `MappingLine`.

## Implementation

- `SourceMap::offsetColumns()` now distinguishes existing empty generated-line spans from missing lines.
- Existing empty lines validate positive column overflow before no-oping, matching upstream `MappingLine::offset_columns()`.
- Missing generated lines and negative empty-line offsets remain no-ops, preserving already accepted parity.
- `wordpress-source-map-vlq-offsets.php` now self-tests the WordPress generated CSS map path for this overflow guard without changing the serialized map.

## Verification

- Baseline before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 test files, 314 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 test files, 317 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 test files, 4620 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SourceMap assertions move from 314 to 317.
- Full LightningCSS lane assertions move from 4617 to 4620.
- Conservative mapped coverage moves from 2163 to 2164 of 3532 for `parcel_sourcemap::MappingLine::offset_columns` empty generated-line positive-overflow guard behavior.

## Dependency Closure

No new support component is needed. This reuses the lane-local native Source Map v3/Base64 VLQ parser and adds no Node, Rust, browser service, external source-map library, or live-service dependency.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice. This patch does not repeat accepted raw VLQ import/remapping, generated-only segments, line/column import offsets, negative generated-line offsets, duplicate generated-column offset/lookup behavior, empty-line negative column-offset no-ops, past-EOF line spans, `addSourceMap()` replacement/consumption, input-map extension, project-root normalization, JSON/data URL defaults, or buffer round trips. It is limited to the remaining empty generated-line positive column-overflow guard in source-map offset handling.
