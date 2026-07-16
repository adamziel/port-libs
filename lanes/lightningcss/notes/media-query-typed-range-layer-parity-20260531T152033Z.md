# LightningCSS Typed Media Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T152033Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted files:
  - `src/media_query.rs` `MediaFeatureType::allows_ranges`, which allows range syntax for `Length`, `Number`, `Integer`, `Resolution`, `Ratio`, and `Unknown` feature values while rejecting boolean and ident range syntax.
  - `src/media_query.rs` `MediaFeatureId`, which types `aspect-ratio` / `device-aspect-ratio` as ratio, `device-width` / `device-height` as length, `color-index`, `monochrome`, and viewport segment features as integer, and vendor device-pixel-ratio features as number.
  - Existing accepted `src/lib.rs::test_media` range/layer behavior remains the surrounding denominator cluster.

## Red-First Evidence

Before the patch, a focused PHP probe rejected upstream-compatible typed media ranges:

- `(min-aspect-ratio: 16 / 9)`
- `(color-index >= 2)`
- `(1 <= monochrome <= 4)`
- `(device-width <= 480px)`
- `(horizontal-viewport-segments >= 2)`

## Native Delta

- `MediaQueryParser` now uses a typed media-feature range map instead of the narrow `width` / `height` / `color` / `resolution` allow-list.
- Range validation now distinguishes length, ratio, integer, number, and resolution values, so invalid typed ranges still fail.
- Legacy target fallback lowering now emits upstream-style `min-` / `max-` aliases for standard typed range features inside layered `@media` rules.
- Vendor pixel-ratio range parsing is accepted for minification but excluded from generic legacy fallback aliasing to avoid invalid `min--webkit-*` serialization.
- `wordpress-media-range-layer-prefixer.php` now covers aspect-ratio and color-index fallbacks inside an `@layer` block.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 402 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 1865 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: exited `0` and emitted typed media range fallback output plus `invalid-media-query`.
- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: all four files report no syntax errors.
- `php -r 'foreach (["lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json", "lanes/lightningcss/lane-status.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'`
  - Result: both JSON files decode successfully.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

## Status Delta

- PHP focused lane evidence: `1843 -> 1865 pass / 0 fail`.
- Conservative mapped coverage: unchanged at `1262 / 3532`; this deepens the already represented media-query parser/source-behavior cluster rather than adding a new upstream helper row.

## Non-Overlap

This avoids accepted source-map empty-line span/import behavior, x resolution-unit serialization, invalid typed media-query validation, media-range include/exclude feature flags, resolution vendor-prefix emission, cascade-layer merge/minifier behavior, flex/grid/font/property-value clusters, CSSOM shorthand behavior, custom-media import-tail scanner behavior, bundler import-prelude diagnostics, CSS Modules edge cases, and visitor/custom at-rule slices.

## Dependency Closure

No new support component is needed. The slice reuses the native `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, and existing lane-local CSS scanner/minifier paths. No upstream binary, browser service, parser generator, or external CSS engine is required.
