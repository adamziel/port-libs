# LightningCSS Media Query Qualifier Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T164206Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted source:
  - `src/media_query.rs::MediaQuery::to_css`, which serializes an explicit qualifier/media type before the condition instead of treating `not screen` as a boolean condition.
  - `src/media_query.rs::QueryFeature::to_css` and `write_min_max`, which compile unsupported range syntax to `min-` / `max-` / `not (...)` feature syntax.
  - `src/lib.rs::test_media`, especially the existing range fallback helper cases around `width > max(10px, 1rem)`.
- Local native-addon oracle at the pinned cache confirmed:
  - `@media not screen and (width < 240px)` with Firefox 60 becomes `@media not screen and not (min-width:240px)`.
  - `@media only screen and (width >= 240px)` with Firefox 60 becomes `@media only screen and (min-width:240px)`.
  - `@media screen and (width > max(10px, 1rem))` with Firefox 60 becomes `@media screen and not (max-width:max(10px,1rem))`.
  - The same behavior is preserved recursively inside `@layer` blocks.

## Red-First Evidence

Before the patch, PHP lowered these query preludes as ordinary boolean conditions:

- `@media not screen and (width < 240px)` became `@media (not screen) and (not (min-width:240px))`.
- `@media screen and (width > max(10px, 1rem))` became `@media screen and (not (max-width:max(10px, 1rem)))`.

Both differ from upstream qualifier/media-type serialization and from upstream function-comma minification in the fallback value.

## Native Delta

- `MediaQueryParser::lowerRangeSyntaxList()` now splits explicit media type prefixes before lowering the condition, preserving `not screen`, `only screen`, custom media types, and `all` elision during fallback lowering.
- Single lowered `>` / `<` range features after an explicit media type no longer receive the extra boolean-operation parentheses that are only needed for top-level condition conjunctions.
- Media feature values now compact function comma spacing in this parser path, so `max(10px, 1rem)` serializes as `max(10px,1rem)` while preserving required calc operator spaces.
- `wordpress-media-range-layer-prefixer.php` now covers print/screen-qualified layered block CSS in addition to the existing range/equality/typed/unknown/resolution cases.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 483 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2321 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: exited `0` and emitted expected Safari/Firefox/Chrome/forced fallback output plus invalid-media-query guards.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

## Status Delta

- Full LightningCSS PHP evidence: `2313 -> 2321 pass / 0 fail`.
- Conservative mapped coverage: unchanged at `1446 / 3532`; this deepens the already represented `src/lib.rs::test_media` range/layer cluster.

## Non-Overlap

This avoids accepted media range target-threshold basics, equality lowering, typed ranges, unknown/custom ranges, invalid known range validation, resolution vendor-prefix emission, resolution `x` unit serialization, include/exclude media feature flags, cascade-layer merging, custom-media import-tail scanner behavior, CSS Modules, source-map, bundler, CSSOM, property/color/grid/font, and custom at-rule visitor slices. The stale 2026-05-25 CustomMedia rework note is unrelated to this media-query qualifier path and predates accepted CustomMedia scanner integrations.

## Dependency Closure

No new support component is needed. This reuses the native `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, and existing lane-local CSS scanner/minifier paths. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.
