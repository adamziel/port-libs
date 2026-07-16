# LightningCSS Media Query Ratio Math Range Layer Parity - 2026-06-01

Slice: `lightningcss-media-query-range-layer-parity-20260601T092802Z`

Base accepted HEAD: `c5d5f0d16396d91eb61e17860e23daa5d67075e3`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream source: `src/media_query.rs` parses media range feature values through typed value parsers, and `src/values/ratio.rs` serializes ratios after math simplification.
- Local pinned native-addon probe confirmed these layered outputs:
  - `@media (aspect-ratio >= max(1/2, 1/3))` -> `@media (aspect-ratio>=.5)`.
  - Firefox 60 target fallback for the same range -> `@media (min-aspect-ratio:.5)`.
  - `@media (1 <= aspect-ratio <= max(2, 3))` -> `@media (1<=aspect-ratio<=3)`.
  - Unknown `theme-ratio >= clamp(1/4, 1/2, 3/4)` -> `@media (theme-ratio>=.5)`, with Firefox 60 fallback `@media (min-theme-ratio:.5)`.
  - Typed ratio math with incompatible length units, such as `aspect-ratio >= max(1/2, 1px)`, is rejected upstream.

## Red-First Evidence

Before this patch, the PHP port preserved compatible ratio math functions instead of simplifying them:

```text
@layer blocks{@media (aspect-ratio>=max(1/2,1/3)){.foo{color:#ff0}}}
@layer blocks{@media (theme-ratio>=clamp(1/4,1/2,3/4)){.foo{color:#ff0}}}
```

It also accepted typed ratio math functions with incompatible length units where upstream rejects the media query.

## Implementation

- `MediaQueryParser::foldSimpleMathFunction()` now treats ratio values as comparable math inputs.
- Ratio operands such as `1/2` are reduced to numeric comparison values for `min()`, `max()`, and `clamp()` before serialization.
- Typed ratio math functions reject incompatible unit-bearing arguments, matching the upstream media query parser.
- Existing length, number, and unknown media-feature math handling remains in the same value path.
- Added focused parser assertions, target-fallback assertions, invalid typed-ratio assertions, and WordPress block-theme example smoke coverage.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-calc-range-layer-prefixer.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 1693 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-calc-range-layer-prefixer.php --self-test`
  - Result: exited `0`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 7159 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss`
  - Result: clean.

Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `7142 -> 7159`.
- Conservative mapped coverage remains `2365 / 3532`; this deepens the already represented media-query range/layer parity cluster instead of claiming a new upstream inventory row.

## Non-Overlap

This does not repeat accepted media range target fallbacks, typed/unknown/equality ranges, env values, resolution prefixes, x/dppx serialization, escaped condition-function rejection, explicit media-type validation, boolean wrapper flattening, import graph media conjunction, CSS Modules, source maps, CSSOM, custom at-rule, target-prefixing, or property-value slices. The behavior here is only ratio-valued math function folding and invalid typed-ratio math rejection in media range/layer parsing and fallback lowering.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `MediaQueryParser`, `TransitionPrefixer`, existing focused tests, and the lane-local WordPress media calc range layer example. No Node/Rust/WASM dependency is required for runtime behavior.

## Next Task

Continue with non-overlapping LightningCSS media-query parser recovery/serialization, bundle/import graph, SourceMap, CSS Modules, CSSOM, custom-at-rule, target-prefixing, and property-value parity.
