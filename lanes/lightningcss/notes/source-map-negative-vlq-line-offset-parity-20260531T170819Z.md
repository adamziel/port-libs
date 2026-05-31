# Source Map Negative VLQ Line-Offset Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T170819Z`
Base: `568c1f2dc06c3f218e0ebf7f60d307c632e8dd1c`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior:
  - `SourceMap::add_vlq_map` starts `generated_line` at the supplied `line_offset`.
  - Raw VLQ segments on negative generated lines are decoded and skipped, but their relative source, original line/column, and name deltas still advance the decoder state.
  - Once a later semicolon moves the generated line to zero, the first emitted mapping uses the accumulated source/original/name state plus the per-line column offset.

## Native PHP Delta

- `SourceMapTest.php` now pins a raw mapping string, `AAEIA;ACGEC`, imported with `lineOffset = -1` and `columnOffset = 4`.
- The focused assertion verifies the skipped prelude mapping is not emitted, while the later mapping emits as `ICKMC` with source index `1`, original line `5`, original column `6`, and name index `1`.
- `wordpress-source-map-vlq-offsets.php` now self-tests the same behavior for generated theme/block CSS maps that drop a wrapper/prelude line before publishing source maps.

## Verification

- Before this patch, focused source-map evidence was `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 139 assertions, 0 failures.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 149 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2555 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 139 to 149.
- Full LightningCSS PHP evidence moves from 2545 to 2555 pass / 0 fail.
- Conservative mapped coverage moves from 1553 to 1554 of 3532 for the additional `parcel_sourcemap::SourceMap::add_vlq_map` negative-line-offset behavior.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/VLQ support with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ generated-only/name import, positive line/column import offsets, source/name/content remapping, invalid index guards, `offsetColumns()`/`offsetLines()` empty-line spans, unsigned overflow guards, `addSourceMap()` line replacement, `extendWithSourceMap()`, project-root normalization, JSON/data URL import defaults, CSS Modules, CSSOM, bundler, media-query, target-prefixing, or custom-at-rule work. It is limited to the raw VLQ negative generated-line offset path where skipped mappings still affect later relative decoder state.
