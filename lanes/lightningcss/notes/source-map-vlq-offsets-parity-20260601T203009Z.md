# LightningCSS Source Map VLQ Offsets Parity - 2026-06-01 20:30Z

Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T203009Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS delegates source-map mutation to `parcel_sourcemap 2.1.1`.
- Upstream `parcel_sourcemap-2.1.1/src/lib.rs::SourceMap::add_vlq_map()` imports source/name/sourceContent tables before decoding mappings, resets the generated column to `column_offset` after each `;`, updates source/original/name relative state while generated lines are skipped by a negative `line_offset`, and only emits mappings when `generated_line >= 0`.

## Native Delta

- Added focused `SourceMapTest.php` coverage for a raw VLQ map with two skipped generated lines before the first kept mapping.
- The skipped mappings update relative source/original/name state, the kept row serializes from that state, and a reused normalized source has its `sourcesContent` updated before decoding.
- Added `wordpress-source-map-skipped-vlq-state.php --self-test` for block-theme source maps with skipped wrapper/prelude rows and a reused SCSS source.
- No production source change was required; the current native PHP SourceMap implementation already matched this upstream behavior.

## Verification

- `php -l lanes/lightningcss/tests/SourceMapTest.php` - pass.
- `php -l lanes/lightningcss/examples/wordpress-source-map-skipped-vlq-state.php` - pass.
- `php lanes/lightningcss/examples/wordpress-source-map-skipped-vlq-state.php --self-test` - `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 1098 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 9065 assertions, 0 failures`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SourceMap assertions move `1085 -> 1098` (`+13`).
- Full LightningCSS lane assertions move `9052 -> 9065` (`+13`).
- Conservative mapped coverage remains `2439 / 3532`; this deepens the already represented source-map VLQ offset cluster.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP Source Map v3/Base64 VLQ implementation, buffer round trip support, and example harness. No Node, Rust, WASM, browser service, external source-map package, live service, or credential-bearing input was introduced.

## Non-Overlap

This does not repeat accepted raw VLQ all-skipped table preservation, one-line skipped relative deltas, generated-only skipped state, skipped-line negative-column underflow guards, duplicate generated-column offset boundaries, empty generated-line spans, direct `add_sourcemap` skipped child table import, pruned input-map appends, source-map data URL parsing, buffer snapshot basics, CSS Modules, CSSOM, media-query, target-prefixing, bundle/import graph, property-value, or custom at-rule work. It is limited to multi-line skipped raw VLQ relative-state carry plus reused normalized source content during table import.
