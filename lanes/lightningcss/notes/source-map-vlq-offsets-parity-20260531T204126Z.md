# Source Map All-Skipped VLQ Offset Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T204126Z`
Base: `91b42fe7029899440b4b46f38b3f903a76f3b322`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from pinned `Cargo.lock`.
- Targeted upstream file: `parcel_sourcemap-2.1.1/src/lib.rs::add_vlq_map()`.
- Source-truth behavior: `add_vlq_map()` registers the input source, sourceContent, and name tables before decoding mappings. If a negative `line_offset` shifts every decoded mapping before generated line zero, upstream skips all generated mappings but still retains the imported tables.
- Local upstream probe against the pinned crate confirmed raw `AAAAA;AACA` with `line_offset = -3` serializes with empty `mappings`, preserves source content and names, and returns an empty mapping list.

## Native PHP Delta

- `SourceMapTest.php` now pins the all-skipped raw VLQ import path with two imported sources, two source contents, and two names.
- `wordpress-source-map-vlq-offsets.php` now self-tests the same path for generated theme/block CSS diagnostics where a wrapper/prelude map is fully dropped by generated-line offsetting.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record the focused assertion growth and conservative mapped coverage movement.

## Verification

- Before this patch, focused source-map evidence was `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 283 assertions, 0 failures.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 289 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 4187 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `php -r 'foreach (["lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json", "lanes/lightningcss/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " OK\n"; }'`: both JSON files OK.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

## Status

- Focused SourceMap assertions move from 283 to 289.
- Full LightningCSS PHP evidence moves from 4181 to 4187 pass / 0 fail.
- Conservative mapped coverage moves from 2078 to 2079 of 3532 for the additional `parcel_sourcemap::SourceMap::add_vlq_map` all-skipped negative-line-offset table-preservation behavior.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, external source-map library, or live service.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 generated-only/name imports, byte-stream no-comma parsing, negative raw-VLQ line-offset remapping with surviving mappings, negative column-offset imports, duplicate generated-column offset or lookup behavior, `offsetColumns()`/`offsetLines()`/`addEmptyMap()` basics, empty generated-line spans, `addSourceMap()` line replacement/consumption, input-map extension, project-root normalization, JSON/data URL defaults, source/name/mapping getters, buffer round trips, bundle SourceMap source collection, CSS Modules, CSSOM, media-query, target-prefixing, or custom-at-rule work. It is limited to preserving imported raw VLQ source/name/content tables when every decoded mapping is skipped by a negative generated-line offset.
