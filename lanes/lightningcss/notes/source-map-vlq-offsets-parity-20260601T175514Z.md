# LightningCSS source-map VLQ offsets parity - 2026-06-01 17:55Z

Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T175514Z`
Base accepted HEAD: `8dd045fd84aaeb764f56a5de3954983c8ce7e870`

## Source truth

- Pinned LightningCSS upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS source-map behavior is delegated to `parcel_sourcemap` 2.1.1.
- `parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::add_sourcemap()` moves child sources, names, and `sources_content` into the parent before iterating child mapping lines and applying `line_offset`.
- Because table import happens before negative-offset line filtering, a child map whose mappings are skipped by `line_offset = -1` can still update a reused parent source's `sourcesContent`, import child names, and then drain the child map.

## Native delta

- Added focused `SourceMapTest.php` coverage for direct `addSourceMap($child, -1)` where the child mapping is skipped but its reused source content and names are still imported.
- Added `wordpress-source-map-direct-skipped-child-content.php` as a WordPress-facing smoke for block editor source maps that keep updated original SCSS content even when a wrapper/prelude child mapping is offset before generated line zero.
- No production source change was required; the native PHP SourceMap implementation already matched this upstream table-before-offset behavior, and this slice pins it with PHP assertions.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php` - pass.
- `php -l lanes/lightningcss/examples/wordpress-source-map-direct-skipped-child-content.php` - pass.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 1073 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-direct-skipped-child-content.php --self-test` - `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 8884 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - pass.

Root harness: not run - isolated micro-slice.

## Status delta

- Focused SourceMap assertions move `1062 -> 1073` (`+11`).
- Full LightningCSS lane assertions move `8873 -> 8884` (`+11`).
- Conservative mapped coverage remains `2399 / 3532`; this deepens the already represented source-map VLQ/add_sourcemap offset cluster rather than claiming a new denominator row.

## Dependency closure

No new support component is needed. This reuses the lane-local native PHP Source Map v3 table handling, Base64 VLQ writer, negative line-offset merge path, and WordPress example harness. No Rust, Node, WASM, browser service, external source-map library, live service, or credential-bearing input is introduced.

## Non-overlap

This does not repeat accepted pruned inline input-map `sourcesContent` preservation, generated-only input-map pruning, raw VLQ negative column resets, duplicate generated-column offsets, byte-stream no-comma parsing, empty-line span replacement, project-root normalization, data URL parsing, buffer round trips, CSS Modules, CSSOM, media-query, target-prefixing, or bundle/import graph work. It is limited to direct upstream `add_sourcemap` table import when negative generated-line offsets skip the child mappings.
