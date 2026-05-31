# LightningCSS Media Query Negated Interval Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T184847Z`

Base: `0c0eec061390da3a2185ec8623476b5865dd4a49`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source:
  - `src/media_query.rs::MediaCondition::Not`, where negated conditions preserve operation grouping when lowered for unsupported range syntax.
  - `src/media_query.rs::QueryFeature::Interval` and `write_min_max`, where `200px <= width < 500px` lowers to `(min-width: 200px) and (not (min-width: 500px))` for older targets.
  - `src/media_query.rs::test_negated_interval_parens`, where `screen and not (200px <= width < 500px)` becomes `screen and not ((min-width: 200px) and (not (min-width: 500px)))` for Chrome 95.
- This deepens the already represented `src/lib.rs::test_media` / `src/media_query.rs` media range fallback cluster rather than claiming a new denominator row.

## Red-First Evidence

Before implementation, direct probes showed:

- `MediaQueryParser::lowerRangeSyntaxList('(hover) and (not (200px <= width < 500px))')` emitted invalid fallback text containing `min-width:not (200px)`.
- `MediaQueryParser::minifyList('(hover) and (not (width < 240px))')` emitted `(hover) and ((width>=240px))` with an extra wrapper.
- `TransitionPrefixer` had the same redundant wrapper after target fallback, producing `@media (hover) and ((min-width:240px))`.

## Native Delta

- `MediaQueryParser::lowerRangeSyntaxCondition()` now returns lowered unwrapped conditions directly instead of forcing a new wrapper around every nested lowering result.
- `MediaQueryParser::lowerRangeFeature()` now refuses to parse `not ...` as a range feature, so negated interval conditions are handled by condition lowering instead of the interval regex.
- `MediaQueryParser::invertNegatedSimpleRangeConditions()` now skips the surrounding wrapper when replacing `(not (...))` simple range conditions, matching upstream's simpler boolean operation shape.
- Focused parser, minifier, prefixer, and WordPress media-range layer example coverage now assert the upstream `Not(Interval)` fallback and wrapper-removal behavior.

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
  - Result: `2 test files, 660 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - Result: exited `0`, including `@layer theme.blocks{@media (hover) and (not ((min-width:200px) and (not (min-width:500px)))){.wp-block-query.is-not-middle-hover{color:#ff0}}}`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 3146 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `3141 -> 3146 pass / 0 fail`.
- Conservative mapped coverage remains `1696 / 3532`.

## Non-Overlap

- Does not repeat accepted media double-negation layer parsing, negated grouped range fallback, calc() range spacing, compound resolution prefix rewriting, all-media elision, explicit media-type OR guard minification, typed/unknown/equality ranges, bundled media boolean conjunction, source-map, CSS Modules, CSSOM, property-value, target-prefixing, or custom-at-rule visitor clusters.
- The stale 2026-05-25 CustomMedia import-tail rework note was inspected and excluded as unrelated to this media range/layer slice; current accepted tests already cover the custom-media import-tail scanner behavior it named.

## Dependency Closure

No new support component is needed. The slice reuses native `MediaQueryParser`, `CssMinifier`, `TransitionPrefixer`, focused PHP tests, and the lane-local WordPress media-range layer example. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.

## Next Task

Continue with non-overlapping LightningCSS media-query parser recovery/serialization, target-prefix browser-boundary cases, CSSOM, CSS Modules, SourceMap, bundler, property-value/font/grid/color, or custom-at-rule parity.
