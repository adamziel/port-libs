# Source Map VLQ Overflow Guard Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T183343Z`
Base: `1d7de15e4e85a2b8dbfd1c80922d2921091d0371`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from pinned `Cargo.lock`, specifically `src/vlq_utils.rs::read_relative_vlq()`.
- Lower-level VLQ source truth: `vlq` 0.5.1 `src/lib.rs::decode()`, which rejects Base64 VLQ byte streams whose shifted accumulated value overflows before relative coordinate arithmetic.
- Red-first PHP probe before the fix: `SourceMap::decodeVlq('//////////////D')` returned generated column `1`, and `SourceMap::addVlqMap('//////////////D', [], [], [])` serialized as `C`; upstream rejects that VLQ stream before it can wrap.

## Native PHP Delta

- `SourceMap::readVlqValue()` now checks shift, shifted digit, and accumulated value overflow before applying relative generated/source/original/name coordinate offsets.
- `SourceMapTest.php` pins the overflow byte stream through both public `decodeVlq()` and raw Source Map v3 `addVlqMap()` import paths.
- `wordpress-source-map-vlq-offsets.php` self-tests the same guard for generated block/theme source-map diagnostics.

## Verification

- `php -l lanes/lightningcss/src/SourceMap.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 203 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 3062 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `php -r 'foreach (["lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json", "lanes/lightningcss/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo "$f OK\n"; }'`: both JSON files OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 201 to 203.
- Full LightningCSS PHP evidence moves from 3060 to 3062 pass / 0 fail.
- Conservative mapped coverage moves from 1684 to 1685 of 3532 for the `parcel_sourcemap`/`vlq` overflow guard before source-map relative coordinate math.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 generated-only/name imports, byte-stream no-comma parsing, negative raw-VLQ line-offset import, duplicate generated-column offset semantics, `offsetColumns()`/`offsetLines()`/`addEmptyMap()`, empty generated-line spans, `addSourceMap()` line replacement, `extendWithSourceMap()` input remapping, project-root normalization, JSON/data URL defaults, source/name/mapping getters, buffer round trips, bundle SourceMap source collection, CSS Modules, CSSOM, media-query, target-prefixing, or custom-at-rule work. The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice.
