# Source Map Negative Line Offset Bounds Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T203119Z`
Base: `29362e0d6ada0a9ddb4cefdc699cee6add41d488`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream files:
  - `parcel_sourcemap-2.1.1/src/lib.rs::offset_lines()`, which removes negative line-offset ranges through `mapping_lines.drain(line - abs_offset..line)`.
  - That upstream drain path only has an in-range removal model for non-empty maps; the PHP port previously allowed a request beyond the known generated-line span to partially remove trailing empty lines.

## Native PHP Delta

- `SourceMap::offsetLines()` now rejects negative generated-line offsets whose removal end is beyond the known generated-line count.
- Valid one-past-end removals still work, preserving upstream trailing empty-line span behavior.
- `SourceMapTest.php` adds a focused regression for positive padding, valid trailing removal, out-of-range negative removal rejection, and no mutation after the guard.
- `wordpress-source-map-vlq-offsets.php` self-tests the guard for generated WordPress theme/block source-map spans.

## Verification

- Red-first probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; $m=new PortLibs\LightningCSS\SourceMap(); $s=$m->addSource("x.css"); $m->addMapping(0,0,$s,0,0); $m->offsetLines(5,2); echo $m->writeVlq(), "\n"; $m->offsetLines(9,-2); echo $m->writeVlq(), "\n";'`
  - Output was `AAAA;;;;;;;` then `AAAA;;;;;;`, showing silent partial mutation instead of a guard.
- `php -l lanes/lightningcss/src/SourceMap.php && php -l lanes/lightningcss/tests/SourceMapTest.php && php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 287 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 4154 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `php -r 'foreach (["lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json", "lanes/lightningcss/lane-status.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'`: both JSON files OK.
- `git diff --check -- lanes/lightningcss`: passed.

## Status

- Focused SourceMap assertions move from 283 to 287.
- Full LightningCSS PHP evidence moves from 4150 to 4154 assertions / 0 failures.
- Conservative mapped coverage moves from 2078 to 2079 of 3532 for the parcel_sourcemap negative generated-line offset bounds behavior.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/Base64 VLQ implementation with no Node, Rust, browser service, or external source-map package.

## Non-Overlap

This slice does not repeat accepted raw Source Map v3 import, raw no-comma byte-stream VLQ parsing, duplicate generated-column offset/search behavior, negative raw-VLQ column offsets, overflow guards, empty generated-line span preservation, add_sourcemap line replacement/consumption, skipped child source table preservation, project-root normalization, JSON/data URL defaults, source/name/mapping getters, buffer round trips, bundle source-map collection, CSS Modules, CSSOM, media-query, target-prefixing, parser recovery, or custom at-rule work. The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this source-map slice.
