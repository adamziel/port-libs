# Source Map Empty Line Span Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T145700Z`
Base: `a187757827b58c999a1fc6cda2f4be5e163b73e9`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior:
  - `SourceMap::offset_lines` stores empty generated `MappingLine` spans when inserting generated lines at or beyond the last concrete mapping line. `write_vlq` serializes those spans as trailing semicolons.
  - `SourceMap::add_sourcemap` replaces parent mapping lines with every child `MappingLine`, including empty trailing child lines, instead of only replacing lines that contain mappings.

## Native PHP Delta

- `SourceMap` now tracks the generated-line span separately from concrete mappings.
- `writeVlq()` serializes empty generated lines represented by source-map line offsets.
- `offsetLines()` updates the generated-line span for upstream-style insertion and removal semantics.
- `addSourceMap()` uses the child map's generated-line span when replacing parent lines, so empty child lines clear parent mappings.
- `wordpress-source-map-vlq-offsets.php` now self-tests an inline editor source map whose trailing empty generated lines survive VLQ serialization.

## Verification

- Red-first focused run before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` failed on the two new cases because the previous implementation emitted `AAAA` instead of `AAAA;;` and left parent mappings on child empty lines.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 83 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 1769 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- `php -l lanes/lightningcss/src/SourceMap.php && php -l lanes/lightningcss/tests/SourceMapTest.php && php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`: no syntax errors.
- `php -r 'foreach (["lanes/lightningcss/lane-status.json", "lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " OK\n"; }'`: both JSON files decode.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

## Status

- PHP pass evidence moves from 1759 to 1769 assertions.
- Conservative mapped coverage moves from 1232 to 1234 of 3532 for two `parcel_sourcemap` empty-line span behaviors.
- Dependency closure: no new support component is needed; this extends the lane-local native Source Map v3/VLQ support and adds no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ import/remapping, generated-only segments, line/column offset import, `offsetColumns`/basic `offsetLines`/`addEmptyMap`, or previous `addSourceMap` line replacement coverage. It specifically covers the missing empty generated-line span storage that upstream `parcel_sourcemap` preserves for offset and nested-map replacement operations.
