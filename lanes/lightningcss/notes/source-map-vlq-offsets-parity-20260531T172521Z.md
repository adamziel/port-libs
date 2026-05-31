# Source Map Relative VLQ Guard Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T172521Z`
Base: `629821655cf6e1a021b6ef13725146c72cabed56`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- `parcel_sourcemap-2.1.1/src/vlq_utils.rs::read_relative_vlq()` rejects cumulative relative VLQ coordinates that become negative or exceed `u32::MAX`.
- `parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()` uses that guard for generated columns, source indexes, original lines, original columns, and name indexes while reading raw Source Map v3 mappings.

## Native PHP Delta

- `SourceMap::decodeVlq()` now validates cumulative generated/source/original/name offsets through the same unsigned 32-bit non-negative guard used by the native raw-map import path.
- `SourceMapTest.php` adds focused coverage for the maximum valid generated column and invalid negative/overflow generated, source, original, and name coordinate deltas.
- `wordpress-source-map-vlq-offsets.php` now self-tests the same invalid VLQ guard for generated theme/block source-map diagnostics.

## Verification

- Red-first probe before implementation: `SourceMap::decodeVlq("D")` returned a decoded mapping with generated column `-1`; `SourceMap::decodeVlq("ggggggI")` returned generated column `4294967296`.
- `php -l lanes/lightningcss/src/SourceMap.php && php -l lanes/lightningcss/tests/SourceMapTest.php && php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 156 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2666 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

## Status

- Focused SourceMap assertions move from 149 to 156.
- Full LightningCSS PHP evidence moves from 2659 to 2666 pass / 0 fail.
- Conservative mapped coverage moves from 1566 to 1567 of 3532 for the `parcel_sourcemap` relative VLQ coordinate guard.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 import/remapping, generated-only segments, positive line/column offset imports, negative raw-VLQ line-offset import, `offsetColumns()`/`offsetLines()`/`addEmptyMap()`, empty generated-line spans, `addSourceMap()` line replacement, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, public offset overflow guards, CSS Modules, CSSOM, bundler, media-query, or target-prefixing work. It is limited to the remaining invalid cumulative Base64 VLQ offset guard for public decoded mappings.
