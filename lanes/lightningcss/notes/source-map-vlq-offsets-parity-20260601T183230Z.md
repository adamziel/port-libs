# LightningCSS source-map VLQ offsets parity - 2026-06-01 18:32Z

Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T183230Z`
Base accepted HEAD: `40d6f27c381f784fcddbd3d62959e60b9072d7b4`

## Source truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1`.
- Upstream `src/lib.rs::SourceMap::add_vlq_map()` initializes `generated_column` from `column_offset`, resets it to that same `column_offset` after every `;`, and calls `read_relative_vlq()` before checking whether `generated_line >= 0`.
- Upstream `src/vlq_utils.rs::read_relative_vlq()` rejects a negative cumulative value. Therefore a generated-column underflow is still an error even when the current generated line will be skipped by a negative line offset.

## Native delta

- Added focused `SourceMapTest.php` coverage for skipped raw VLQ generated lines with negative generated-column underflow:
  - immediate skipped line: `addVlqMap('A', ..., lineOffset: -1, columnOffset: -1)`;
  - later skipped reset: `addVlqMap('C;A', ..., lineOffset: -2, columnOffset: -1)`.
- Both cases reject with no generated mappings while preserving imported `sources`, `sourcesContent`, and `names`, matching upstream table-import-before-decode behavior.
- Extended `wordpress-source-map-negative-column-reset.php --self-test` with the same skipped block stylesheet reset path.
- No production source change was needed; the current native PHP SourceMap implementation already matched this upstream behavior.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php` - pass.
- `php -l lanes/lightningcss/examples/wordpress-source-map-negative-column-reset.php` - pass.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 1085 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-negative-column-reset.php --self-test` - `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 8946 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - pass.

Root harness: not run - isolated micro-slice.

## Status delta

- Focused SourceMap assertions move `1073 -> 1085` (`+12`).
- Full LightningCSS lane assertions move `8934 -> 8946` (`+12`).
- Conservative mapped coverage remains `2399 / 3532`; this deepens the already represented SourceMap VLQ offset cluster.

## Dependency closure

No new support component is needed. This reuses the lane-local native PHP Source Map v3/Base64 VLQ implementation and existing WordPress example harness. No Node, Rust, WASM, browser service, external source-map library, live service, or credential-bearing input is introduced.

## Non-overlap

This does not repeat accepted negative column reset with prior kept rows, negative raw-map line/column offsets with surviving mappings, skipped invalid source/name indexes, duplicate generated-column offsets, byte-stream no-comma parsing, empty generated-line spans, add_sourcemap table import, pruned inline input maps, project-root normalization, data URL parsing, CSS Modules, CSSOM, media-query, target-prefixing, bundle/import graph, property-value, or custom-at-rule work. It is limited to generated-column underflow validation that occurs before the skipped-generated-line filter in raw VLQ import.
