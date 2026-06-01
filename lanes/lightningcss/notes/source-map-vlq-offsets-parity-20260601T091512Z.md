# SourceMap VLQ Offset Parity 2026-06-01T09:15:12Z

## Scope

This isolated LightningCSS slice pins one upstream-backed SourceMap merge edge: a child map merged with a negative `add_sourcemap` line offset where child line 0 is skipped, child line 1 is an interior empty span that clears parent line 0, and child line 2 survives as a retained child mapping at parent line 1.

## Source Truth

- LightningCSS upstream manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS source-map behavior delegates to `parcel_sourcemap` 2.1.1.
- `parcel_sourcemap::SourceMap::add_sourcemap` moves each child mapping line to `line + line_offset`, skips negative target lines, replaces each surviving parent line with the child `MappingLine`, remaps source/name tables, and consumes the child source map with `std::mem::take`.
- `MappingLine::offset_lines` can create empty child-line spans, so an interior empty child line is observable as a replacement/clear when it survives a merge offset.

## Native PHP Coverage

- Added `SourceMapTest.php` coverage for the exact VLQ output `;ICECM;ADADJ;AACAC;AACAC`.
- The test verifies decoded line/column/source/name deltas, source content and name remapping, `findClosestMapping()` behavior for the cleared gap and retained child mapping, binary buffer round trip, and child-map consumption.
- Extended `wordpress-source-map-trailing-empty-offset.php --self-test` with a WordPress block stylesheet source-map scenario covering the same interior empty negative-offset merge behavior.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 758 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-trailing-empty-offset.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7093 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-trailing-empty-offset.php` -> no syntax errors.
- `git diff --check -- lanes/lightningcss` -> passed.

## Non-overlap

This deepens the already represented SourceMap/VLQ offset cluster and does not claim new mapped denominator growth. It is distinct from accepted positive-offset trailing empty spans, leading empty negative spans, direct `offsetLines()` movement, duplicate generated-column offsets, unsorted VLQ columns, and child-consumption-only coverage.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `SourceMap` implementation and existing lane test/example harnesses.
