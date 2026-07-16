# Source Map Leading VLQ Offset Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T215025Z`
Base: `9ef60eb910c3006c081a236c1ec05f4d0e7024c4`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream files:
  - `parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()`, which starts at the supplied line/column offsets, treats raw semicolons as generated-line advances, and resets generated columns to the column offset after each semicolon.
  - `parcel_sourcemap-2.1.1/src/lib.rs::offset_lines()`, which splices empty `MappingLine` records at the requested generated-line index, including index zero.
  - `parcel_sourcemap-2.1.1/src/lib.rs::add_sourcemap()`, which replaces parent lines with every child mapping line that survives the merge offset, including empty child lines after earlier child lines are skipped by a negative offset.
- Local pinned-crate probe confirmed these VLQ outputs:
  - leading raw semicolon import with `line_offset = 2`, `column_offset = 3`: `;;;;GACA`;
  - first-line insertion/removal around an existing map: `;;AAAAA;;IAECC` then `;AAAAA;;IAECC`;
  - negative child-map merge where the skipped first child line still lets trailing empty child lines clear parent lines: `;;AAEAE;AACAC`.

## Native PHP Delta

- `SourceMapTest.php` adds a focused upstream parity case for leading raw VLQ semicolon offsets, generated-line insertion before the first mapping, follow-up negative removal, and nested negative `addSourceMap()` empty-line replacement.
- `wordpress-source-map-vlq-offsets.php` now self-tests the same source-map offset paths for generated theme/block CSS maps.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record the focused assertion growth and conservative mapped coverage movement.

## Verification

- Before this patch, focused SourceMap evidence was `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 306 assertions, 0 failures.
- `php -l lanes/lightningcss/tests/SourceMapTest.php && php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 325 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 4533 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 306 to 325.
- Full LightningCSS PHP evidence moves from 4514 to 4533 assertions / 0 failures.
- Conservative mapped coverage moves from 2152 to 2153 of 3532 for the additional `parcel_sourcemap` leading raw VLQ and nested line-offset span behavior.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, external source-map library, or live service.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 generated-only/name imports, positive line/column raw-map import offsets, negative raw-VLQ line-offset imports with surviving mappings, all-skipped raw-VLQ table preservation, negative column-offset imports, duplicate generated-column offset/search behavior, empty generated-line column-offset no-ops, `offset_lines` past-EOF spans, `addEmptyMap()` CR line splitting, `addSourceMap()` consumption, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, buffer round trips, bundle SourceMap source collection, CSS Modules, CSSOM, media-query, target-prefixing, or custom-at-rule work. The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice.
