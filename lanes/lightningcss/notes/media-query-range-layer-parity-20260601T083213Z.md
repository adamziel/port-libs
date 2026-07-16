# LightningCSS Media Query Range Layer Equality Parity - 2026-06-01 08:32 UTC

Slice: `lightningcss-media-query-range-layer-parity-20260601T083213Z`

Base accepted HEAD: `e307345b68a0844266e5b42b8d4ac54edb9f105d`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream behavior: `src/media_query.rs::MediaCondition::transform_resolution()` clones `resolution` range features to `-webkit-device-pixel-ratio` and `-moz-device-pixel-ratio` while preserving the range operator.
- Upstream lowering boundary: `QueryFeature::to_css()` lowers range syntax only when `MediaRangeSyntax` fallback compilation is active.

## Red-First Evidence

Before this slice, the PHP prefixer preserved the unprefixed modern equality arm when `exclude => ['MediaRangeSyntax']` was set, but its Safari/Firefox cloned arms serialized with colon syntax:

```text
@layer blocks{@media (-webkit-device-pixel-ratio:2),(-moz-device-pixel-ratio:2),(resolution=2dppx){.wp-block-query{color:#ff0}}}
```

That diverged from upstream operator preservation for cloned resolution range features.

## Implementation

- `TransitionPrefixer::matchResolutionEqualityRangeConditions()` now records `operator => '='` for equality range syntax matches.
- Existing prefix clone generation reuses that operator, so Safari/Firefox device-pixel-ratio aliases stay in modern equality syntax when `MediaRangeSyntax` is excluded.
- Existing range-lowering still converts those equality clones to colon syntax when `MediaRangeSyntax` fallback output is requested.
- Added a focused `@layer` assertion to `TransitionPrefixerTest`.
- Added the same WordPress block-theme example case to `wordpress-media-range-layer-prefixer.php`.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` - `1 test files, 1110 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test` - exited 0.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 6943 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - no whitespace errors.

## Status Delta

- `lane-status.json` `phpPass`: `6942 -> 6943`.
- Conservative mapped coverage remains `2360 / 3532`; this deepens an already represented media-query range/layer target-prefix cluster.

## Non-Overlap

This slice does not repeat existing range fallback, interval fallback, x/dppx unit serialization, unitless/range recovery, CSS Modules, CSSOM, source-map, or bundle/import graph parity slices. It is limited to resolution equality prefix clone syntax inside cascade layers when range syntax is intentionally preserved.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `TransitionPrefixer`, the existing media query parser behavior, the focused lane test harness, and the existing WordPress media range layer example.

## Next Task

Continue with non-overlapping media query planner/parser parity, especially invalid typed range diagnostics, mixed media-list layer/import interactions, or source-map/CSSOM/CSS Modules clusters that are not already represented by current accepted coverage.
