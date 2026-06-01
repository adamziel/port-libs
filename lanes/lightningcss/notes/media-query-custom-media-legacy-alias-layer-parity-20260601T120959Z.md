# Media Query Custom Media Legacy Alias Layer Parity

Date: 2026-06-01
Micro-slice: `lightningcss-media-query-range-layer-parity-20260601T120959Z`
Pinned upstream: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Source Truth

The pinned upstream `src/media_query.rs::MediaFeatureName::parse` only turns `min-` / `max-` legacy aliases into range comparisons when the stripped feature name parses as a known standard feature. If the stripped name is unknown, upstream keeps the original identifier as an unknown media feature with no comparison. This means a custom-media definition like `not (min-Theme-Breakpoint: 2)` stays a negated unknown feature, while `not (min-width: 300px)` still compacts to a width range.

## Red-First Evidence

Before the fix, the PHP custom-media transformer incorrectly lowercased and inverted unknown legacy-looking feature names:

```sh
php tools/run-tests.php lanes/lightningcss/tests/CustomMediaTransformerTest.php
```

The new focused case failed with:

```text
Expected: '@layer theme.blocks{@media not (min-Theme-Breakpoint:2){.min{color:#ff0}}@media not (max---WP-Breakpoint:4){.max{color:#00f}}}'
Actual: '@layer theme.blocks{@media (theme-breakpoint<2){.min{color:#ff0}}@media (--wp-breakpoint>4){.max{color:#00f}}}'
```

The WordPress custom-media example self-test failed with the same `theme-breakpoint<2` inversion before the source change.

## Implementation

`CustomMediaTransformer::simplifyNegatedFeatureRanges()` now delegates matched negated legacy aliases to `MediaQueryParser::minifyList()`. Known standard aliases still receive the upstream-style double-wrapped custom-media range condition, while unknown/custom legacy-looking names remain ordinary negated media features with authored casing preserved.

The WordPress custom-media example now self-checks a layered block query alias using `not (min-Theme-Breakpoint: 2)`.

## Verification

- `php -l lanes/lightningcss/src/CustomMediaTransformer.php`
  - no syntax errors
- `php -l lanes/lightningcss/tests/CustomMediaTransformerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-custom-media-transformer.php`
  - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CustomMediaTransformerTest.php`
  - `1 test files, 42 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-media-transformer.php --self-test`
  - exited `0` and printed the expected minified WordPress CSS
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7695 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - passed

Root harness: not run - isolated micro-slice.

## Status Delta

`lane-status.json` now reports `phpPass: 7695` and `phpFail: 0`.

Conservative mapped coverage remains `2374 / 3532` because this deepens the already represented media-query/custom-media range cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP custom-media transformer, media query parser, minifier, and WordPress example smoke path.

## Non-Overlap

This does not repeat prior direct `MediaQueryParser` custom range case preservation, negated modern custom range parsing, media range fallback lowering, percentage-length rejection, import media tails, redundant boolean wrapper flattening, resolution prefixing, CSS Modules, source-map, CSSOM, custom-at-rule, or property-value slices. It is limited to custom-media expansion of negated legacy `min-` / `max-` aliases inside layered CSS.
