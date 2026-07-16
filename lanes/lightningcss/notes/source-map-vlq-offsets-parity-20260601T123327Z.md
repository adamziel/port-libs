# Source Map Same-Line Child Merge Parity - 2026-06-01

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T123327Z`
Base accepted HEAD: `c0f466b52b24855ebf2184044c0a755725b1aa01`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1`.
- Inspected upstream `src/lib.rs::SourceMap::add_sourcemap()`, `write_vlq()`, `mapping_line.rs`, and `vlq_utils.rs`.
- A local Rust oracle using the same `parcel_sourcemap` crate confirmed exact VLQ `AAAAA,UAAKC;ECQJO,I,GAAMC;ADNPL,UAAKC;AACLC,UAAKC` for a same-line child source-backed/generated-only/source-backed replacement at line offset 1.

## Native Delta

- Added focused SourceMap coverage where a child line with three segments replaces a parent line with multiple mappings.
- Verifies the generated-only child segment remains unmapped and does not disturb source/name deltas before the next source-backed child segment.
- Added a WordPress block-theme source-map smoke for a card block map merged into a theme parent map.

## Verification

- Baseline full LightningCSS lane: 13 files / 7771 assertions / 0 failures.
- `php -l lanes/lightningcss/tests/SourceMapTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-same-line-child-merge.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php`: 1 test files / 899 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-source-map-same-line-child-merge.php --self-test`: OK.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 test files / 7788 assertions / 0 failures.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

## Coverage Delta

Focused SourceMap assertions moved 882 -> 899. Full LightningCSS lane moved 7771 -> 7788. Conservative mapped coverage remains 2392 / 3532 because this deepens an already represented parcel_sourcemap add_sourcemap/VLQ cluster.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `SourceMap` implementation and existing Source Map v3/Base64 VLQ support.

## Non-Overlap

No current LightningCSS rework note existed for this lane before editing. This slice does not repeat raw VLQ import, empty child spans, generated-only child line gaps, rejected child merge, duplicate/unsorted column offsets, data URL import, input-map extension, CSS Modules, CSSOM, bundle/import, media, target-prefix, property-value, or custom-at-rule slices. It is limited to valid same-line `add_sourcemap` replacement with an interleaved generated-only segment.
