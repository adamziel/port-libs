# LightningCSS Source Map VLQ Offsets Parity

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T161750Z`
Base accepted HEAD: `ec3bcd9ad95b8f5fb0e5f5fb2227076702e7d642`

## Source Truth

Pinned LightningCSS upstream is `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

The source-map behavior comes from `parcel_sourcemap` 2.1.1, used by LightningCSS. In `src/lib.rs::add_vlq_map`, `generated_column` is initialized from `column_offset` and reset back to `column_offset` after each `;`. In `src/vlq_utils.rs::read_relative_vlq`, a decoded relative value whose cumulative result is negative is rejected. That means a raw map can preserve mappings imported before a later line reset underflows, while still keeping imported source/name/sourceContent tables.

## Implemented Behavior

- Added focused PHP coverage for source-backed raw VLQ import `KAAAA;A` with `column_offset = -3`. The first mapping survives at generated column `2`, the second-line reset underflows and throws, and the imported source, content, and name tables remain available.
- Added generated-only coverage for raw VLQ import `K;A` with the same negative column reset behavior.
- Added `wordpress-source-map-negative-column-reset.php` as a block-stylesheet smoke that exercises the same partial-preservation behavior without Node/Rust.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors detected
- `php -l lanes/lightningcss/examples/wordpress-source-map-negative-column-reset.php` -> no syntax errors detected
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 1015 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-negative-column-reset.php --self-test` -> `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8652 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` -> passed

## Delta And Non-Overlap

SourceMapTest moved from `998` to `1015` assertions, adding `17` focused assertions. Full LightningCSS PHP lane moved from `8635` to `8652` assertions. Mapped upstream coverage remains `2398 / 3532` because this deepens the existing source-map VLQ offset cluster instead of adding a new manifest unit.

This does not overlap the already accepted source-map coverage for initial negative column offsets, duplicate generated columns, generated-only child pruning, data URLs, or invalid imported indexes. This slice covers the later semicolon reset path where an earlier imported row remains observable after a subsequent generated-column underflow.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `SourceMap` VLQ decoder/encoder, buffer round-trip, and example self-test harness.
