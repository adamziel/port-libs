# Source Map Duplicate Boundary Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T022019Z`
Base accepted HEAD: `28ec15ab9aa5188bc23d7c22caf22b5083cf6e4e`

## Source Truth

- Pinned LightningCSS upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map behavior to `parcel_sourcemap` `2.1.1`, pinned in `Cargo.lock`.
- Relevant source files inspected locally from the cached crate:
  - `parcel_sourcemap-2.1.1/src/mapping_line.rs::MappingLine::offset_columns()`
  - `parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::add_vlq_map()`

Focused upstream probe against the pinned crate:

```text
before vlq AAAAA,EACAC,AACAC,GACAC
after  vlq AAAAA,EACAC,AAEAE
after mappings: generated columns 0, 2, 2; original lines 0, 1, 3; names 0, 1, 3
```

This pins the duplicate generated-column boundary for a negative column offset:
the earlier duplicate at the removal start is preserved, the later duplicate is
drained, the mapping at the offset boundary shifts into the duplicate column,
and the imported source/name/sourceContent tables remain available.

## Native PHP Delta

- `SourceMapTest.php` adds focused coverage for the duplicate generated-column
  negative offset boundary using a raw Source Map v3 VLQ import.
- `wordpress-source-map-vlq-offsets.php` self-tests the same boundary for a
  block/theme source map and checks closest-mapping behavior after the shift.
- `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` record the measured
  assertion delta. Mapped coverage remains conservative because this deepens the
  already represented `parcel_sourcemap::MappingLine::offset_columns` cluster.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 446 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5461 assertions, 0 failures`

Focused SourceMap assertions moved from `436` to `446`. Full lane PHP
assertions moved from `5451` to `5461`.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local Source Map
v3 and Base64 VLQ implementation with no Node, Rust runtime dependency, browser
service, external source-map package, or live-service credential.

## Non-Overlap

The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is
unrelated to this source-map slice. This does not repeat accepted raw v3 import,
byte-stream no-comma parsing, generated-only segments, positive/negative raw-map
line and column offsets, fully skipped table preservation, duplicate column
positive/ordinary negative offsets, unsorted line sorting, empty-span behavior,
`addSourceMap()`, `extendWithSourceMap()`, project-root normalization,
JSON/data URL/buffer round-tripping, CSS Modules, CSSOM, media-query,
target-prefix, bundle/import, or custom at-rule work. It is limited to the
negative offset removal-start boundary when duplicate generated columns are
present on the same VLQ mapping line.
