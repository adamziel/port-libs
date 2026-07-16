# Source Map Skipped Same-Line VLQ Offset Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T012211Z`
Base accepted HEAD: `b9bbeca66ecf5a12b5cede18d997f59a57398d59`

## Source Truth

- Pinned LightningCSS upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream source-map implementation is delegated through `parcel_sourcemap` `2.1.1`, pinned in `Cargo.lock`.
- Relevant source files inspected locally from the cached crate:
  - `parcel_sourcemap-2.1.1/src/lib.rs` `SourceMap::add_vlq_map()`
  - `parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `parcel_sourcemap-2.1.1/src/vlq_utils.rs` `read_relative_vlq()`

The behavior selected for this slice is that `add_vlq_map()` keeps source,
original-position, and name relative-state updates for multiple mappings on a
generated line that is skipped by a negative generated-line offset. Those
updates affect the first surviving mapping on a later generated line.

Local upstream probe against `parcel_sourcemap` `2.1.1`:

```text
raw map: AAAAA,CAAC;ACCEC
line_offset: -1
column_offset: 3
write_vlq: GCCGC
surviving mapping: generated line 0, generated column 3, source 1, original line 1, original column 3, name 1
```

## Native PHP Delta

- Added focused `SourceMapTest.php` coverage for the skipped same-line raw VLQ
  relative-state case.
- Extended `wordpress-source-map-vlq-offsets.php` self-test with the same
  source-map path for block-style source maps emitted after skipped generated
  prelude mappings.
- The existing native PHP source-map decoder already matched upstream behavior;
  this slice adds the missing parity guard and example evidence.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php && php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`
  - `1 test files, 436 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5335 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - passed

Focused SourceMap assertions moved from `426` to `436`. Full lane PHP
assertions moved from `5325` to `5335`. Mapped coverage remains conservative at
`2288 / 3532` because this deepens the existing `parcel_sourcemap add_vlq_map`
source-map cluster.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local Source Map
v3 and Base64 VLQ implementation.

## Non-Overlap

This does not repeat accepted raw v3 import, byte-stream no-comma parsing,
generated-only segment parsing, positive/negative raw-map line and column
offsets, fully skipped table preservation, duplicate generated-column handling,
unsorted line sorting, empty-span behavior, `addSourceMap()`,
`extendWithSourceMap()`, project-root normalization, JSON/data URL/buffer
round-tripping, CSS Modules, CSSOM, media-query, target-prefix, bundle/import,
or custom at-rule work. It is limited to skipped same-line raw VLQ relative
state under a negative generated-line offset.
