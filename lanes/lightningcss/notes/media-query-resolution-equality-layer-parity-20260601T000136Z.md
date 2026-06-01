# LightningCSS Media Query Resolution Equality Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T000136Z`

Base: `0e78c232d5f671d5140ddac2287b4ff3c85d5779`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source:
  - `src/media_query.rs::MediaCondition::transform_resolution`, where any `resolution` range feature, including equality, is transformed into WebKit/Mozilla device-pixel-ratio fallbacks for old targets.
  - `src/media_query.rs::QueryFeature::to_css` and `write_min_max`, where equality ranges lower to plain feature syntax such as `(resolution:2dppx)` and `(-webkit-device-pixel-ratio:2)`.
  - `src/values/resolution.rs`, where `x` and `dppx` are equivalent resolution units.
  - `src/prefixes.rs::Feature::AtResolution`, where older Safari/iOS Safari and Firefox targets require resolution media-query prefixes.
- This deepens the already represented media-query range/layer and resolution-prefix clusters rather than claiming a new denominator row.

## Red-First Evidence

Before implementation, a direct probe showed that range lowering worked but legacy equality prefixes were missing:

```text
@layer blocks{@media (resolution:2dppx){.wp-block-query{color:#ff0}}}
```

The expected upstream-shaped old-target output includes prefixed equality alternatives before the standard lowered query:

```text
@layer blocks{@media (-webkit-device-pixel-ratio:2),(-moz-device-pixel-ratio:2),(resolution:2dppx){.wp-block-query{color:#ff0}}}
```

## Native Delta

- `TransitionPrefixer` now recognizes direct equality-form `resolution` range conditions before lowering in addition to the existing lowered `min-resolution` and `max-resolution` paths.
- Old Safari/Firefox targets now emit `-webkit-device-pixel-ratio` and `-moz-device-pixel-ratio` equality fallbacks while preserving the standard `(resolution:...)` branch.
- Authored legacy plain `(resolution:...)` queries remain unprefixed, matching upstream's distinction between plain media features and range features.
- Focused parser coverage confirms equality range lowering from `x` to `dppx` before prefix expansion.
- The WordPress media-range layer example now covers exact-density block styling inside `@layer theme.blocks`.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 1125 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - Result: exited `0`, including `@media (-webkit-device-pixel-ratio:2),(-moz-device-pixel-ratio:2),(resolution:2dppx)` for exact-density block CSS.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 4987 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moves `4983 -> 4987` assertions, `0` failures.
- Conservative mapped denominator coverage remains `2216 / 3532` because this is additional coverage inside the already represented media-query resolution-prefix/range-layer cluster.

## Non-Overlap

- Does not repeat accepted x-resolution unit serialization, compound/fractional resolution prefixing, media-query conjunction, comment-token layer parsing, negated interval ranges, typed/unknown/equality width ranges, all-media elision, invalid range validation, SourceMap, CSS Modules, CSSOM, bundle/import graph, custom at-rule, or property-value clusters.
- The stale 2026-05-25 custom-media import-tail rework note was inspected and excluded as unrelated to this resolution equality media-query target-prefix slice; accepted source already contains later custom-media/import-tail behavior.

## Dependency Closure

No new support component is needed. The slice reuses native `MediaQueryParser`, `TransitionPrefixer`, focused PHP tests, and the lane-local WordPress media-range layer example. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.

## Next Task

Continue with non-overlapping LightningCSS media-query parser recovery/serialization, target-prefix browser-boundary cases, CSSOM, CSS Modules, SourceMap, bundler, property-value/font/grid/color, or custom-at-rule parity.
