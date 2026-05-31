# Source Map add_sourcemap Line Replacement Parity - 2026-05-31

Slice: `lightningcss-source-map-vlq-offsets-parity-20260531T134455Z`
Base: `cfc0ceacf5641bb15b4805d1b71ed57135565616`

## Upstream Evidence

- LightningCSS pinned source: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map helper source truth: `parcel_sourcemap` 2.1.1 from the pinned LightningCSS `Cargo.lock`.
- Targeted upstream behavior: `SourceMap::add_sourcemap` remaps child sources, names, and source contents, then replaces each target generated `MappingLine` represented by the child map's generated-line span. Empty child gap lines clear existing target mappings, and negative line offsets drop child lines before generated line zero instead of appending duplicate generated segments.

## Native PHP Delta

- `SourceMap::addSourceMap()` now groups remapped child mappings by generated line, removes existing parent mappings for the child generated-line span after applying the line offset, and inserts the remapped child lines.
- The existing WordPress source-map example now seeds a placeholder generated mapping before merging the block stylesheet map; the upstream-style merge removes that placeholder and keeps the emitted VLQ JSON stable.

## Verification

- Red-first focused test before implementation: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` failed in `source map replaces overlapped source-map lines when merging nested maps` because the old implementation appended overlapped line mappings.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 file, 73 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 1586 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`: OK.
- Root harness: not run - isolated micro-slice.

## Status

- PHP pass evidence moves from 1576 to 1586 assertions.
- Conservative mapped coverage moves from 1141 to 1142 of 3532 for the parcel_sourcemap nested map line-replacement behavior.
- Dependency closure: no new support component is needed; this reuses the lane-local native Source Map v3/VLQ support and adds no Node, Rust, browser service, or external source-map library shell-out.

## Non-Overlap

This slice does not repeat accepted raw VLQ import/remapping, generated-only segments, line/column offset import, offsetColumns/offsetLines/addEmptyMap helpers, CSSOM list-style behavior, or CSS Modules view-transition scoping. It specifically adds the missing `add_sourcemap` overlapped-line replacement behavior used when nested LightningCSS source maps are merged.
