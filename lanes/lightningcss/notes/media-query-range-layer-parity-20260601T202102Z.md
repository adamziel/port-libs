# LightningCSS Media Query Range Layer Parity - 2026-06-01T202102Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-media-query-range-layer-parity-20260601T202102Z`
- Base accepted HEAD: `889f1d709734867fa2d1b9d74be494ea9a1e87a1`
- Upstream source truth: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Source Truth

- `src/lib.rs::test_media` covers media range parsing and canonical printer behavior.
- `src/lib.rs::test_layer` and `src/lib.rs::test_merge_layers` cover cascade layer statement/block ordering.
- `src/bundler.rs` documents and implements wrapping imported rules in `@media`, `@supports`, and `@layer` while preserving authored order, including `CssRule::LayerStatement` handling under parent layers.

## Implementation

- `StylesheetParser::parseBody()` now extracts top-level nested at-rule statements from declaration spans before nested block rules. This keeps `@layer reset;` inside `@layer theme.blocks { ... }` as a CSSOM rule instead of passing it to declaration parsing.
- `StylesheetParser::propertyLocation()` path lookup now counts nested at-rule statements while walking into nested blocks, so source-location paths align with the parsed CSSOM rule list.
- `wordpress-cssom-media-range-layer.php` now models a block-theme layer statement before a range-normalized `@media` block and checks the `padding-inline` source location via path `[0, 1, 0]`.

## Evidence

- `php -l lanes/lightningcss/src/StylesheetParser.php`
- `php -l lanes/lightningcss/tests/StylesheetParserTest.php`
- `php -l lanes/lightningcss/examples/wordpress-cssom-media-range-layer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/StylesheetParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `3 test files, 2247 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-cssom-media-range-layer.php --self-test`
  - Result: `OK`

## Counting

- `StylesheetParserTest.php` moved from 37 to 44 assertions, for `+7` focused PHP assertions.
- `lane-status.json` `phpPass` moves `9052 -> 9059`.
- Conservative mapped coverage remains `2439 / 3532`; this deepens already represented media-query range/layer and CSSOM source-location clusters.

## Dependency Closure

No new support component is needed. The slice reuses existing `StylesheetParser`, `CssRule`, `MediaQueryParser`, and `DeclarationBlock` behavior.

## Non-Overlap

This does not repeat the accepted `calc(infinity)` media range/layer, layered import-tail media range lowering, resolution x-unit serialization, media target-prefix fallback, or custom at-rule visitor clusters. The patch is scoped to CSSOM parsing/path parity for nested layer statements before media range blocks.
