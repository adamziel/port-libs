# LightningCSS source-map VLQ offsets parity - 2026-06-01 15:07Z

Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T150737Z`

Base accepted HEAD: `4d56b5fdd17417a158c91428202c0f41403853f8`

## Source Truth

- Upstream pinned LightningCSS checkout: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/printer.rs` inline input-map remapping calls `find_closest_mapping` and emits no output mapping when the closest upstream mapping has no original source.
- `src/bundler.rs` suppresses the generated imported CSS source entry when an inline `data:` source map is present, so the printer/input-map remap is the source of truth for source-backed rows.

## Patch

- `SourceMap::appendSourceMapWithGeneratedOffset(..., false)` now skips generated-only child mappings while still preserving generated line spans and source-backed VLQ offsets.
- Default append and `addSourceMap()` behavior still preserve generated-only segments, matching generic `parcel_sourcemap::SourceMap::add_sourcemap` behavior.
- Added a WordPress-facing example showing a generated-only inline source-map row is pruned while later source-backed rows keep their generated-line and generated-column offsets.

## Red / Green Evidence

- New focused assertion initially exposed the pre-fix output as `AAAAA;;I,KCECC;G,KACCC`, which retained generated-only child rows at the remapped offsets.
- After the source change, the same case emits `AAAAA;;SCECC;QACCC`, containing only the entry mapping plus the source-backed child rows.

## Verification

- `php -l lanes/lightningcss/src/SourceMap.php` - pass.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` - pass.
- `php -l lanes/lightningcss/examples/wordpress-source-map-input-remap-source-backed.php` - pass.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 979 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 793 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-input-remap-source-backed.php --self-test` - `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 8341 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP `SourceMap` and `CssBundler` source-map plumbing; Rust/Node/WASM upstream runners remain unexecuted for this isolated lane slice.

## Non-Overlap

Avoided the accepted CSSOM list-style/counter and color-adjust target-prefix surfaces. This patch is limited to inline input source-map VLQ/generated-offset remapping under `lanes/lightningcss`.
