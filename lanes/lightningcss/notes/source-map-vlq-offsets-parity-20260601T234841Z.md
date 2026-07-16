# LightningCSS Source Map VLQ Offsets Parity - 2026-06-01 23:48Z

Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T234841Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1` from the pinned `Cargo.lock`.
- Source files inspected:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/port-libs/.upstream-cache/lightningcss/src/bundler.rs`
- Relevant upstream behavior:
  - `parcel_sourcemap::SourceMap::add_sourcemap()` remaps child source indexes, then imports `sources_content` with `set_source_content()` for the remapped source index before applying child mapping lines.
  - `lightningcss/src/bundler.rs` avoids adding the generated imported CSS source entry when an inline `data:` source map is present, leaving the input source map/printer remap to provide source-backed rows and content.

## Native Delta

- `SourceMap` now tracks which `sourcesContent` entries were explicitly set rather than sparse placeholders created while setting later source indexes.
- JSON-decoded maps that omit or null out `sourcesContent` keep placeholder serialization but no longer treat those placeholders as explicit content during later input-map appends.
- `SourceMap::appendSourceMapWithGeneratedOffset(..., false)` now imports child content for a reused source when the parent source table entry exists but has no explicit content yet.
- Existing first-imported-content behavior is preserved: a later pruned append for the same reused source does not overwrite already imported content.
- Added `wordpress-source-map-pruned-reused-content.php` to self-test a block stylesheet path where an inline Sass input map reuses a predeclared source entry.

## Verification

- Before this patch: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 1113 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 1135 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-pruned-reused-content.php --self-test` - `OK`.
- `php -l lanes/lightningcss/src/SourceMap.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-pruned-reused-content.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests` - `14 test files, 9208 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SourceMap assertions move `1113 -> 1135` (`+22`).
- Full LightningCSS lane assertions move `9186 -> 9208` (`+22`).
- Conservative mapped coverage remains `2439 / 3532`; this deepens the already represented SourceMap generated-offset/input-map remap cluster.
- Full Rust/Node/WASM upstream runners were not executed in this isolated slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP Source Map v3/Base64 VLQ implementation, generated-offset append path, source table normalization, and example harness. No Node, Rust runtime dependency, WASM, browser service, external source-map package, live service, or credential-bearing input was introduced.

## Non-Overlap

This patch is limited to pruned generated-offset input-map appends where the parent source table already has a normalized source entry but no explicit content yet. It does not repeat accepted raw VLQ import/remapping, negative line or column offset guards, duplicate generated-column offset/search behavior, unsorted line sorting, empty generated-line span serialization, `add_sourcemap` replacement/consumption without generated offsets, generated-only pruning, reused source content preservation after a first import, input-map extension, project-root normalization, data URL parsing, CSS Modules, CSSOM, media-query, target-prefixing, bundle/import graph, property-value, or custom at-rule work.
