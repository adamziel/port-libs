# LightningCSS Media Query X Resolution Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T221915Z`

Base: `b5eea1a41c2dcbd3b034814e155f2555fc5c0b4e`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source:
  - `src/values/resolution.rs`, where both `dppx` and `x` parse as the same `Resolution::Dppx` value, and printing falls back to `dppx` unless the targets support `Feature::XResolutionUnit`.
  - `src/media_query.rs::MediaList::transform_resolution` and `MediaCondition::transform_resolution`, where resolution media queries emit WebKit/Mozilla device-pixel-ratio variants for unsupported targets.
  - `src/prefixes.rs::Feature::AtResolution`, where older Safari/iOS Safari and Firefox targets require resolution media-query prefixes.
  - `src/lib.rs::test_media` and `test_resolution`, which exercise media query range lowering and target-aware resolution unit serialization.
- This deepens the already represented media-query range/layer and resolution-prefix clusters rather than claiming a new denominator row.

## Red-First Evidence

Before implementation, direct probes showed that old-target output kept authored `x` units and skipped resolution media-prefix fallbacks:

- `@layer blocks { @media (min-resolution: 2x) { .wp-block-query { color: yellow; } } }` with `['safari' => 15, 'firefox' => 10]` emitted only `@media (min-resolution:2x)` instead of WebKit/Mozilla device-pixel-ratio alternatives plus a `dppx` fallback.
- `@layer blocks { @media (resolution: 1x) { ... } }` with `['chrome' => 50]` serialized as `1x` even though upstream prints `dppx` for targets without `XResolutionUnit`.

## Native Delta

- `MediaQueryParser` now exposes `useDppxResolutionUnitList()` and rewrites `x` resolution values to `dppx` in both feature-first and value-first range syntax.
- `TransitionPrefixer` now normalizes authored `x` resolution units to `dppx` for old targets before resolution media-prefix expansion, while keeping modern target output on `x`.
- Resolution prefix ratio conversion now accepts authored `x` units so old Safari/Firefox targets can emit `-webkit-*-device-pixel-ratio` and `*-moz-device-pixel-ratio` variants from `x`-authored queries.
- The WordPress media-range layer example now covers legacy `x` serialization and prefixed `x` range/interval fallbacks inside `@layer theme.blocks`.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: `1 test files, 306 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 694 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - Result: exited `0`, including `@media (-webkit-min-device-pixel-ratio:2),(min--moz-device-pixel-ratio:2),(min-resolution:2dppx)` and interval `x` fallback output.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 4623 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `4617 -> 4623 pass / 0 fail`.
- Conservative mapped coverage remains `2163 / 3532`.

## Non-Overlap

- Does not repeat accepted escaped media identifiers, redundant nested-negation range/layer behavior, decimal/fractional `dppx` resolution prefixing, compound `dppx` resolution range prefixing, typed/unknown/equality media range fallback, SourceMap, CSS Modules, CSSOM, custom at-rule, property-value, or bundle/import graph clusters.
- The stale 2026-05-25 CustomMedia import-tail rework note was inspected and excluded as unrelated to this x-resolution range/layer target-prefix slice; accepted source already contains later custom-media/import-tail scanner coverage.

## Dependency Closure

No new support component is needed. The slice reuses native `MediaQueryParser`, `TransitionPrefixer`, focused PHP tests, and the lane-local WordPress media-range layer example. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.

## Next Task

Continue with non-overlapping LightningCSS media-query parser recovery/serialization, target-prefix browser-boundary cases, CSSOM, CSS Modules, SourceMap, bundler, property-value/font/grid/color, or custom-at-rule parity.
