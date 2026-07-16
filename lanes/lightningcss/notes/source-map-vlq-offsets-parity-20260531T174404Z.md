# Source Map Byte-Stream VLQ Offset Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T174404Z`
Base: `b1feedb755e93656cf717884940e8c64724c26f1`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- `parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()` reads raw mappings as a byte stream. After generated/source/original/name values, it only stops when the next byte is `;` or `,`; otherwise the next byte is read as the next mapping's generated-column delta.
- `parcel_sourcemap-2.1.1/src/vlq_utils.rs::read_relative_vlq()` applies the same unsigned relative-coordinate guard used by the previous SourceMap VLQ offset slices.

## Native PHP Delta

- `SourceMap::addVlqMap()` now reads one Base64 VLQ value at a time instead of decoding a comma-split segment in bulk.
- Raw Source Map v3 input such as `AAAAAA` is imported like upstream: a source-backed named mapping followed immediately by a generated-only mapping on the same line.
- `SourceMap::decodeVlq()` follows the same byte-stream parser, so public debug/inspection output matches imported raw-map behavior.
- `wordpress-source-map-vlq-offsets.php` now self-tests the same raw-map path with generated line and column offsets, serializing the canonical `;;IAAAA,A;K` form.

## Verification

- Red-first probe before implementation: `SourceMap::fromJson(... "mappings":"AAAAAA" ...)` threw `InvalidArgumentException: Invalid source map segment: AAAAAA`.
- `php -l lanes/lightningcss/src/SourceMap.php && php -l lanes/lightningcss/tests/SourceMapTest.php && php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 179 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 2809 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

## Status

- Focused SourceMap assertions move from 164 to 179.
- Full LightningCSS PHP evidence moves from 2794 to 2809 pass / 0 fail.
- Conservative mapped coverage moves from 1601 to 1602 of 3532 for the additional `parcel_sourcemap::SourceMap::add_vlq_map` byte-stream parsing behavior.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 generated-only/name imports with proper comma separators, line/column raw-map import offsets, negative raw-VLQ line-offset import, relative VLQ guard failures, `offsetColumns()`/`offsetLines()`/`addEmptyMap()`, empty generated-line spans, `addSourceMap()` line replacement, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, public offset overflow guards, CSS Modules, CSSOM, bundler, media-query, or target-prefixing work. It is limited to upstream's permissive raw VLQ byte-stream parser when adjacent mappings are not comma-separated.
