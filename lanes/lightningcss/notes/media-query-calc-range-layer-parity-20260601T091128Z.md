# LightningCSS Media Query Calc Range Layer Parity - 2026-06-01 09:11 UTC

Slice: `lightningcss-media-query-range-layer-parity-20260601T091128Z`

Base accepted HEAD: `8c8829e6ea966fa9e8e7ed89cc2696e6096ac93d`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Native addon oracle: `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`
- Upstream behavior:
  - `@media (width >= calc(2 * 3px))` minifies to `@media (width>=6px)`.
  - Firefox 60 target lowering converts that case to `@media (min-width:6px)`.
  - `@media (width >= calc(2 * 3))` minifies to `@media (width>=6)` and lowers to `@media (min-width:6)`.
  - `@media (width > max(1, 2))` minifies to `@media (width>2)` and lowers to `@media not (max-width:2)`.
  - Plain `@media (width >= 2)` still lowers to `@media (min-width:2px)`.
  - Invalid typed multiplicative calc operands such as `calc(6px / 2px)`, `calc(6 / 2px)`, and `calc(6px * 2px)` are rejected.

## Red-First Evidence

Before this slice, the PHP parser preserved multiplicative calc expressions in media range values instead of folding them:

```text
(width>=calc(2*3px))
(width>=calc(6px/2))
```

The local legacy fallback path also lost unitless math-function origin after minification, producing `not (max-width:2px)` for `width > max(1, 2)` instead of upstream's `not (max-width:2)`.

## Implementation

- `MediaQueryParser` now folds simple `calc()` multiplication and division when exactly one operand carries a unit, or when both operands are unitless.
- Unitless values folded from `calc()`, `min()`, `max()`, and `clamp()` no longer receive an automatic `px` length unit; plain numeric length range inputs still do.
- Invalid simple multiplicative calc values with two dimensional multiplication operands, dimensional divisors, or zero divisors are rejected in typed ranges.
- Legacy target range lowering carries an internal unitless-math marker through the pre-minify prefixer pipeline so `TransitionPrefixer` can preserve upstream unitless `max(1, 2)` fallbacks while still lowering plain `width >= 2` to `min-width:2px`.
- Added focused parser and transition-prefixer assertions, plus a WordPress block-theme media range example covering calc product, calc quotient, and unitless math fallbacks inside `@layer`.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php` - no syntax errors.
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php` - no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` - no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php` - no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` - `1 test files, 529 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` - `1 test files, 1144 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test` - exited 0.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 7095 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - no whitespace errors.

## Status Delta

- `lane-status.json` `phpPass`: `7075 -> 7095`.
- Conservative mapped coverage remains unchanged; this deepens the already represented media-query range/layer target-prefix cluster.

## Non-Overlap

This slice does not repeat prior media range fallback, interval fallback, resolution equality prefix clone, escaped condition function rejection, invalid feature recovery, CSS Modules, CSSOM, source-map, property-value, or bundle/import graph slices. It is limited to calc multiplication/division and unitless math serialization in media range values, including legacy target fallbacks inside cascade layers.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP media query parser, transition prefixer, CSS minifier, focused test harness, local pinned upstream LightningCSS addon for oracle checks, and the existing WordPress media range layer example.

## Next Task

Continue with non-overlapping media query parser recovery, import graph, source-map, CSS Modules, CSSOM, property-value, custom at-rule, or target-prefix parity slices.
