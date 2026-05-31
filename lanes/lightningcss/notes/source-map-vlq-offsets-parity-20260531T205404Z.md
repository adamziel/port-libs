# Source Map offset_lines Past-EOF Span Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T205404Z`
Base: `4cd5c83f2f1b57c5b3e318d737d8c94ee34892b6`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream file: `parcel_sourcemap-2.1.1/src/lib.rs::offset_lines()`.
- Source-truth behavior: when `generated_line > mapping_lines.len()` and the line offset is positive, upstream calls `ensure_lines(line + abs_offset)` and preserves empty mapping-line spans through VLQ serialization. A later negative `offset_lines()` drains the requested empty-line range and shifts later mappings down.

## Native PHP Delta

- `SourceMapTest.php` adds a focused parity case for offsetting line 4 by +2 in a one-line map, preserving the serialized `AAAA;;;;;;` empty generated-line span.
- The same test adds a later mapping at generated line 6, then applies a negative line offset that drains empty lines and serializes the shifted mapping as `AAAA;;;;EAMA`.
- `wordpress-source-map-vlq-offsets.php` self-tests the same path for generated block/theme CSS source maps.

## Verification

- Before this patch, focused SourceMap evidence was `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 288 assertions, 0 failures.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 295 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 4269 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 288 to 295.
- Full LightningCSS PHP evidence moves from 4262 to 4269 pass / 0 fail.
- Conservative mapped coverage moves from 2093 to 2094 of 3532 for `parcel_sourcemap::SourceMap::offset_lines` past-EOF span behavior.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, external source-map library, or live service.

## Non-Overlap

The stale May 25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice. This patch does not repeat accepted raw Source Map v3 generated-only/name imports, line/column raw-map import offsets, negative raw-VLQ line-offset import, relative VLQ guard failures, byte-stream no-comma parsing, duplicate generated-column offset or lookup behavior, `offsetColumns()` guards, `addEmptyMap()` CR line splitting, `addSourceMap()` line replacement/consumption, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, buffer round trips, bundle SourceMap source collection, CSS Modules, CSSOM, media-query, target-prefixing, or custom-at-rule work. It is limited to `offset_lines()` creating and later draining empty generated-line spans when the insertion point is beyond the current mapping table.
