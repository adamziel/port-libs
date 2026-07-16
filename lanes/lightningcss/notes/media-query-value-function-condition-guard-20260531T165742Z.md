# LightningCSS Media Query Value Function Condition Guard

Slice: `lightningcss-media-query-range-layer-parity-20260531T165742Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted source:
  - `src/media_query.rs::parse_query_condition`, where media conditions start with a parenthesis block or `not`; top-level `Token::Function` is only accepted for style/scroll-state container-query flags, not for media queries.
  - `src/media_query.rs::parse_parens_or_function`, which likewise rejects function tokens after media boolean operators for ordinary media conditions.
  - Existing `src/lib.rs::test_media` invalid condition-function coverage around `@media unknown(foo) {}`; this slice extends the same validation boundary to CSS value functions that are only valid inside feature values.

## Red-First Evidence

Before the patch, the native PHP parser accepted invalid top-level media condition functions:

- `MediaQueryParser::minifyList('var(--theme-breakpoint)')` returned `var(--theme-breakpoint)`.
- `MediaQueryParser::minifyList('screen and var(--theme-breakpoint)')` returned `screen and var(--theme-breakpoint)`.
- `CssMinifier` allowed `@layer blocks { @media var(--theme-breakpoint) { ... } }`.

Upstream rejects these as unexpected function tokens. Valid feature-value functions such as `(width > max(10px, 1rem))` remain accepted and still lower through legacy range fallbacks.

## Native Delta

- `MediaQueryParser::validateTopLevelConditionFunctions()` now rejects every top-level function token except the existing `not(...)` condition form.
- Parser, minifier, and prefixer tests cover `calc(...)`, `env(...)`, and `var(...)` at top level and after an explicit media type prefix.
- `wordpress-media-range-layer-prefixer.php` now self-checks that layered block-theme media rules reject top-level custom-property condition functions while preserving the existing range fallback outputs.

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
  - Result: `2 test files, 498 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2368 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
  - Result: exited `0` and emitted expected Safari/Firefox/Chrome/forced fallback output plus invalid-media-query guards.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

## Status Delta

- Full LightningCSS PHP evidence: `2361 -> 2368 pass / 0 fail`.
- Conservative mapped coverage remains `1458 / 3532`; this deepens the already mapped `src/media_query.rs` / `src/lib.rs::test_media` media-query validation cluster rather than claiming a new denominator row.

## Non-Overlap

This avoids accepted equality/typed/unknown media ranges, explicit `not`/`only` media-type fallback serialization, resolution prefix and `x` unit serialization, include/exclude media feature flags, cascade-layer merging, custom-media import-tail scanner behavior, CSS Modules, source-map, bundler, CSSOM, target-prefix, property/color/grid/font, and custom at-rule visitor slices. The stale 2026-05-25 CustomMedia import-tail rework note is unrelated to this parser validation path.

## Dependency Closure

No new support component is needed. This reuses the native `MediaQueryParser`, `TransitionPrefixer`, `CssMinifier`, and existing lane-local scanner/minifier paths. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.
