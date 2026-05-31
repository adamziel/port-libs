# LightningCSS Media Range Equality Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T161210Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted source:
  - `src/media_query.rs` `QueryFeature::to_css`, which calls `write_min_max` when `MediaRangeSyntax` must compile away for legacy targets.
  - `src/media_query.rs` `write_min_max`, where `MediaFeatureComparison::Equal` uses no `min-`/`max-` prefix and serializes equality range features as plain colon media features.
  - `src/media_query.rs` `MediaFeatureValue::parse_unknown`, which keeps unknown/custom equality values such as identifiers and `env(...)` valid.
- Direct local upstream native-addon oracle confirmed:
  - `@layer blocks { @media (width = 240px) ... }` with Firefox 60 becomes `@media (width:240px)`.
  - `@media (240px = width)` lowers to the same colon syntax.
  - `@media (theme-state = expanded)` lowers to `(theme-state:expanded)`.
  - `@media (--wp-breakpoint = env(--wp-breakpoint))` lowers to `(--wp-breakpoint:env(--wp-breakpoint))`.
  - Firefox 64 keeps `(width=240px)`.

## Red-First Evidence

Before the patch, PHP kept equality range syntax for legacy fallback targets:

- `MediaQueryParser::lowerRangeSyntaxList('(width = 240px)')` returned `(width=240px)`.
- `TransitionPrefixer::prefixForTargets('@layer ... @media (width = 240px) ...', ['firefox' => 60])` emitted `@media (width=240px)` instead of upstream `@media (width:240px)`.

## Native Delta

- `MediaQueryParser` now includes `=` in simple range fallback lowering and serializes equality as colon feature syntax.
- Interval range parsing now treats equality operators as invalid interval comparisons before minification, matching upstream's matching-direction-only interval guard.
- Focused tests cover standard equality, value-first equality, unknown ident equality, custom/env equality, modern target preservation, and invalid equality intervals.
- `wordpress-media-range-layer-prefixer.php` now self-checks exact-width and custom state equality fallbacks inside a block-theme `@layer`.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 448 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2102 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: exited `0` and emitted expected legacy colon fallbacks, modern equality ranges, and invalid-media-query guards.
- `php -l lanes/lightningcss/src/MediaQueryParser.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status OK\n";'`
  - Result: `lane-status OK`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

## Status Delta

- Full LightningCSS PHP evidence: `2092 -> 2102 pass / 0 fail`.
- Conservative mapped coverage: unchanged at `1349 / 3532`; this deepens the already represented `src/lib.rs::test_media` and `src/media_query.rs` range/layer cluster rather than adding a new denominator row.

## Non-Overlap

This avoids accepted typed media ranges, unknown/custom non-equality ranges, invalid known range validation, resolution vendor-prefix emission, resolution `x` unit serialization, include/exclude media feature flags, cascade-layer merging, custom-media import-tail scanner behavior, CSS Modules, source-map, bundler, CSSOM, target alpha-color fallback, and custom at-rule visitor slices.

## Dependency Closure

No new support component is needed. This reuses the native `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, and existing lane-local CSS scanner/minifier paths. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.
