# LightningCSS Media Query Parenthesized Negation Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T170819Z`

Base: `568c1f2dc06c3f218e0ebf7f60d307c632e8dd1c`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source: `src/media_query.rs` `MediaCondition::Not::to_css`, `QueryFeature::negate`, and `QueryFeature::to_css` target fallback lowering.
- Local native-addon oracle from the pinned cache confirmed:
  - `@media (not (width < 240px))` minifies to `@media (width>=240px)`.
  - `@media screen and (not (width < 240px))` minifies to `@media screen and (width>=240px)`.
  - `@media (hover) and (not (width < 240px))` preserves the upstream operation wrapper as `@media (hover) and ((width>=240px))`.
  - The same parenthesized negation behavior applies inside `@layer` and in `@import` media tails.

## Red-First Evidence

Before this patch, valid layered CSS like:

```css
@layer blocks {
  @media (not (width < 960px)) {
    .wp-block-query.is-wide { color: chartreuse }
  }
}
```

failed in the native PHP path with `Unknown media query condition function: not(width<960px)` because the preliminary CSS whitespace compactor removed the space in `not (` before `MediaQueryParser` parsed the prelude.

## Native Delta

- `MediaQueryParser::minifyList()` now has an internal `allowCompactedNegation` mode for callers that pass already-compacted CSS.
- `CssMinifier` enables that mode for `@media` preludes and `@import` media tails, where valid source `not (` may already be compacted to `not(` by the first minification pass.
- Parenthesized negated simple ranges are normalized to the upstream positive range form at the top level and after explicit media types, while boolean-operation wrappers such as `(hover) and ((width>=240px))` remain intact where upstream keeps them.
- `wordpress-media-layer-minifier.php --self-test` now exercises the parenthesized negated range form in layered block-theme CSS.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/src/CssMinifier.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/CssMinifierTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `3 test files, 1549 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2554 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test`
  - Result: exited `0`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2545 -> 2554 pass / 0 fail`.
- Conservative mapped coverage remains `1553 / 3532`; this deepens the already represented `src/media_query.rs` and `src/lib.rs::test_media` range/layer cluster rather than adding a new denominator row.

## Non-Overlap

This avoids accepted escaped import specifier decoding, transform target-prefix boundaries, simple top-level negated range normalization, grid area shorthand composition, SourceMap empty-line offset guards, CSS Modules composes priority parsing, typed/unknown/equality range fallback, media feature include/exclude flags, resolution prefixing and `x` units, cascade-layer merging, custom-media scanner behavior, CSSOM, bundler, color/font/grid/property-value, and custom at-rule visitor slices.

## Dependency Closure

No new support component is needed. The slice reuses the native `MediaQueryParser`, `CssMinifier`, `TransitionPrefixer`, and existing lane-local tests/examples. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.
