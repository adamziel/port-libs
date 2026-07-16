# LightningCSS Media Query Double-Negation Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T183234Z`

Base: `1d7de15e4e85a2b8dbfd1c80922d2921091d0371`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source: `src/media_query.rs::MediaCondition::negate`, where `Not(Not(condition))` serializes as the inner condition.
- Targeted fallback source: `src/media_query.rs::write_min_max`, where unsupported `<` ranges lower to `not (min-...)`.
- This deepens the already represented `src/lib.rs::test_media` media range and layer fallback cluster rather than claiming a new denominator row.

## Red-First Evidence

Before implementation:

```bash
php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php
```

Result:

```text
2 test files, 597 assertions, 3 failures
```

The failures showed:

- `not (not (width < 240px))` serialized as `not (not (width<240px))` instead of `(width<240px)`.
- Layered CSS preserved `@media not (not (width<960px))` instead of collapsing to the inner range.
- Legacy range fallback emitted `not (((min-width:240px)))` instead of upstream-compatible `not (min-width:240px)`.

## Native Delta

- `MediaQueryParser` now collapses double-negated conditions before simple negated range inversion.
- Feature conditions keep their normal feature parentheses, while operation conditions keep the wrapper shape expected by existing media boolean serialization.
- Layered block-theme CSS now minifies `@media not (not (width < ...))` to the inner range and prefixes legacy range fallback without stale nested negation wrappers.
- `wordpress-media-layer-minifier.php --self-test` now exercises the double-negated layered responsive query path.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 620 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 3066 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test`
  - Result: exited `0`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `3060 -> 3066 pass / 0 fail`.
- Conservative mapped coverage remains `1684 / 3532`.

## Non-Overlap

This avoids accepted radial/conic gradient minifiers, logical border CSSOM, CSS Modules grid custom-ident composition, escaped-url bundle/custom-media graph handling, custom supports-rule visitor returns, SourceMap buffer round trips, border-image target boundaries, explicit media-type OR guards, calc() range spacing, compound resolution prefixing, all-media elision, x-resolution units, typed/unknown range parsing, and invalid range validation.

## Dependency Closure

No new support component is needed. The slice reuses the native `MediaQueryParser`, `CssMinifier`, `TransitionPrefixer`, focused PHP tests, and lane-local WordPress media-layer smoke. No upstream binary, browser service, parser generator, or external CSS engine is required for runtime behavior.

## Next Task

Continue with non-overlapping LightningCSS media-query validation/recovery, container/media condition serialization, target-prefix browser-boundary cases, CSSOM, CSS Modules, SourceMap, bundler, property-value/font/grid/color, or custom-at-rule parity.
