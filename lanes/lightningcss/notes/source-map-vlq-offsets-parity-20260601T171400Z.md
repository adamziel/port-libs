# LightningCSS source-map VLQ offsets parity - 2026-06-01 17:14Z

Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T171400Z`

## Source truth

- Pinned LightningCSS upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/printer.rs::Printer::add_mapping()` remaps through input source maps by recording the source table length before `map.add_source(...)` and only setting source content when the source was newly inserted.
- Local `parcel_sourcemap-2.1.1` source confirms the same shape: `SourceMap::add_source()` deduplicates sources and `set_source_content()` is a separate write.

## Red-first evidence

Before the source change, a local one-off probe that appended two pruned inline input maps with normalized equivalent original sources produced `sourcesContent` ending in the second child content. Upstream keeps the first source content because the second remap reuses the existing source index and skips the content write.

## Native delta

- `SourceMap::appendSourceMapWithGeneratedOffset(..., false)` now tracks whether each child source was newly added while remapping source-backed child mappings.
- Pruned input-map appends now set `sourcesContent` only for newly inserted remapped sources, preserving the first original content when later inline maps normalize to the same source path.
- Added direct SourceMap VLQ assertions for reused remapped sources, generated offsets, names, and drained child maps.
- Added CssBundler coverage for two imported CSS files with inline source maps pointing at the same normalized SCSS source.
- Added `wordpress-source-map-reused-input-content.php` to cover the WordPress-facing block import path.

## Verification

- `php -l lanes/lightningcss/src/SourceMap.php` - pass.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` - pass.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - pass.
- `php -l lanes/lightningcss/examples/wordpress-source-map-reused-input-content.php` - pass.
- `php lanes/lightningcss/examples/wordpress-source-map-reused-input-content.php --self-test` - `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php lanes/lightningcss/tests/CssBundlerTest.php` - `2 test files, 1913 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 8873 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - pass.

Root harness: not run - isolated micro-slice.

## Status delta

Full lane PHP pass/assertion evidence moves from `8856` to `8873` assertions. Conservative mapped coverage remains `2399 / 3532` because this deepens already represented SourceMap VLQ offset and bundle/import graph source-map clusters.

## Dependency closure

No new support component is needed. The slice reuses the lane-local native PHP Source Map v3, Base64 VLQ, data URL, and CssBundler source-map plumbing; no Rust, Node, WASM, browser, live-service, or external source-map runtime was introduced.

## Non-overlap

This does not repeat accepted generated-only pruning, duplicate generated-column offsets, raw VLQ byte-stream parsing, buffer round-trip, project-root normalization, data URL parser trivia, child line-span replacement, or bundle duplicate inline fragment offset slices. It is limited to reused original-source content preservation during pruned inline input-map remapping.
