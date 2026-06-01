# LightningCSS Source Map VLQ Offsets Parity - 2026-06-01 23:20Z

Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T232058Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1`.
- Source files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
- Relevant upstream behavior: `SourceMap::add_sourcemap` imports source and name tables, refreshes `sources_content` for reused normalized sources, remaps generated lines, and takes/drains child mapping/source/name tables after merge. The lane-local `appendSourceMapWithGeneratedOffset(..., preserveUnusedTables: true)` mirrors that table-preserving import behavior while adding the generated line/first-line column offset used by bundled input maps.

## Native Delta

- Added focused SourceMap coverage for preserve-mode generated-offset appends where:
  - a child source path normalizes to an existing parent source;
  - child `sourcesContent` refreshes the reused parent source content;
  - a generated-only mapping on child line 0 receives the first-line column offset;
  - later child lines keep line-local columns;
  - unused child names are preserved in table-preserving mode; and
  - the child source map is drained after append.
- Added `wordpress-source-map-preserved-offset-content.php` as a block stylesheet smoke for the same reused shared-source source-map path.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 1113 assertions, 0 failures`.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-preserved-offset-content.php` - no syntax errors.
- `php lanes/lightningcss/examples/wordpress-source-map-preserved-offset-content.php --self-test` - `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 9119 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SourceMap assertions move `1100 -> 1113` (`+13`).
- Full LightningCSS lane assertions move `9106 -> 9119` (`+13`).
- Conservative mapped coverage remains `2439 / 3532`; this deepens the already represented SourceMap/generated-offset cluster.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP Source Map v3/Base64 VLQ implementation, source table normalization, generated-offset append helper, and example harness. No Node, Rust runtime dependency, WASM, browser service, external source-map package, live service, or credential-bearing input was introduced.

## Non-Overlap

This covers table-preserving generated-offset append behavior for reused source content and generated-only first-line offsets. It does not repeat accepted empty generated-line negative column no-op behavior, pruned input-map first-content preservation, raw VLQ import/remapping, skipped generated-line relative state, duplicate generated-column offset/search behavior, unsorted line sorting, empty generated-line span serialization, `add_sourcemap` replacement/consumption without generated offsets, input-map extension, project-root normalization, data URL parsing, CSS Modules, CSSOM, media-query, target-prefixing, bundle/import graph, property-value, or custom at-rule work.
