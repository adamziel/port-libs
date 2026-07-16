# Media Query Range Layer Parity - 2026-05-31 21:40 UTC

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream files:
  - `src/lib.rs::test_media` for range syntax minification and target fallback serialization.
  - `src/media_query.rs` for keyword parsing through cssparser identifiers: media qualifiers, media types, `not`, and boolean `and`/`or` are parsed from decoded CSS identifier tokens.

## Implemented Behavior

- `MediaQueryParser` now normalizes escaped CSS identifiers that decode to media-query keywords before validation, minification, and legacy range lowering.
- Covered escaped `only`, `not`, `and`, `or`, and `screen` in parser-level output and inside `@layer { @media ... }` minification.
- The WordPress smoke extends `wordpress-media-escaped-range-layer.php` so block-theme media rules with escaped media keywords still receive old-target range fallbacks.

## Non-overlap

- This does not duplicate accepted relative-color, custom at-rule, source-map, import-graph, or redundant nested-negation media range work.
- It deepens the already represented media-query parser cluster, so conservative mapped coverage remains `2145 / 3532`.
- The stale 2026-05-25 `CustomMediaTransformer` rework note was reviewed and left untouched because the current accepted manifest already contains later custom-media/import-tail work and this slice is assigned to media-query range/layer parsing.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `1 test files, 303 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4461 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-escaped-range-layer.php --self-test`
  - exit `0`
- `php -l lanes/lightningcss/src/MediaQueryParser.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-media-escaped-range-layer.php`
  - no syntax errors
- `git diff --check -- lanes/lightningcss`
  - exit `0`
- JSON decode sanity check for `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json` and `lanes/lightningcss/lane-status.json`
  - both valid

## Dependency Closure

- No new support component is needed. The slice reuses the existing native PHP CSS identifier escape decoder in `MediaQueryParser`.
