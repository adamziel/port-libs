# Source Map Negative Column-Offset Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T190157Z`
Base: `9992592125363999691e76351c839408179ceff4`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from pinned `Cargo.lock`.
- Targeted upstream files:
  - `parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()`, which initializes `generated_column` from the supplied `column_offset`, resets it to that offset after each semicolon, and still decodes skipped negative generated lines.
  - `parcel_sourcemap-2.1.1/src/vlq_utils.rs::read_relative_vlq()`, which permits a negative base offset only when the decoded relative VLQ value brings the cumulative coordinate back into `0..=u32::MAX`.
- Local pinned-crate probe confirmed:
  - `K,I;O` with `line_offset = 0`, `column_offset = -3` serializes as `E,I;I`.
  - `KAAA,IACA;OACA` with `line_offset = 0`, `column_offset = -3` serializes as `EAAA,IACA;IACA`.
  - `KAAA;OACA` with `line_offset = -1`, `column_offset = -3` serializes as `IACA`.

## Native PHP Delta

- `SourceMapTest.php` now pins generated-only, source-backed, and skipped-negative-line raw VLQ imports with negative column offsets.
- The existing native `SourceMap::addVlqMap()` arithmetic is now covered for upstream's negative `column_offset` behavior, including per-line reset and the underflow guard when the first generated-column delta remains negative.
- `wordpress-source-map-vlq-offsets.php` self-tests the same behavior for generated block/theme CSS source-map diagnostics.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record the focused assertion and conservative mapped-coverage movement.

## Verification

- Before this patch, focused source-map evidence was `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 212 assertions, 0 failures.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 225 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 3219 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 212 to 225.
- Full LightningCSS PHP evidence moves from 3206 to 3219 pass / 0 fail.
- Conservative mapped coverage moves from 1721 to 1722 of 3532 for the additional `parcel_sourcemap::SourceMap::add_vlq_map` negative column-offset behavior.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 generated-only/name imports, byte-stream no-comma parsing, positive line/column raw-map import offsets, negative generated-line offset imports with positive columns, duplicate generated-column offset or lookup semantics, relative VLQ guard failures, public offset overflow guards, `offsetColumns()`/`offsetLines()`/`addEmptyMap()` basics, empty generated-line spans, `addSourceMap()` line replacement, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, buffer round trips, bundle SourceMap source collection, CSS Modules, CSSOM, media-query, target-prefixing, or custom-at-rule work. The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice.
