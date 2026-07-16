# Source Map Empty-Line Column Offset Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T211358Z`
Base: `3a3374ad59c06e8a3561833481036dd945373160`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream file: `parcel_sourcemap-2.1.1/src/mapping_line.rs::offset_columns()`.
- Source-truth behavior: `SourceMap::offset_columns()` returns `Ok(())` for an existing empty generated line. Because there are no mappings to drain or shift, positive and negative column offsets leave the VLQ output unchanged.
- Local upstream probe against the pinned crate confirmed a map serialized as `AAAA;;` remains `AAAA;;` after `offset_columns(1, 3, -4)`.

## Native PHP Delta

- `SourceMap::offsetColumns()` now returns immediately when the generated line has no mappings, matching upstream empty `MappingLine` behavior instead of validating the requested column offset.
- `SourceMapTest.php` splits the duplicated empty-line test key into separate focused cases for empty-line column-offset no-ops and empty-line line-offset removals.
- `wordpress-source-map-vlq-offsets.php` now self-tests that generated theme/block CSS source maps keep empty generated-line spans unchanged after positive and negative column-offset calls.

## Verification

- Before this patch, focused source-map evidence was `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 305 assertions, 0 failures.
- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 306 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 4391 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.

## Status

- Focused SourceMap assertions move from 305 to 306.
- Full LightningCSS PHP evidence moves from 4390 to 4391 pass / 0 fail.
- Conservative mapped coverage moves from 2117 to 2118 of 3532 for `parcel_sourcemap::MappingLine::offset_columns` empty generated-line no-op behavior.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, external source-map library, or live service.

## Non-Overlap

The stale May 25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice. This patch does not repeat accepted raw Source Map v3 generated-only/name imports, line/column raw-map import offsets, negative raw-VLQ line-offset import, relative VLQ guard failures, byte-stream no-comma parsing, duplicate generated-column offset or lookup behavior, `addEmptyMap()` CR line splitting, `addSourceMap()` line replacement/consumption, skipped child source table preservation, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, buffer round trips, bundle SourceMap source collection, CSS Modules, CSSOM, media-query, target-prefixing, or custom-at-rule work. It is limited to `offset_columns()` on empty generated-line spans created by source-map line offsets.
