# LightningCSS Media Query Dangling Logic Layer Validation

Slice: `lightningcss-media-query-range-layer-parity-20260531T185124Z`

Base: `0c0eec061390da3a2185ec8623476b5865dd4a49`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source: `src/media_query.rs::MediaQuery::parse_with_options`, where an explicit media type accepts `and` only when followed by a parsed media condition, and `src/media_query.rs::parse_query_condition`, where `and` / `or` operations must be followed by another parenthesized condition or function.
- This deepens the already represented `src/lib.rs::test_media` validation/range cluster. It does not claim a new helper denominator row.

## Red-First Evidence

Before implementation:

```bash
php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php
```

Result:

```text
1 test files, 175 assertions, 2 failures
```

The failing assertions showed that PHP accepted invalid media preludes such as `screen or (hover)`, `(hover) and`, and layered `@media screen and { ... }`.

## Native Delta

- `MediaQueryParser` now rejects top-level `and` / `or` operators that start a media query, appear consecutively, or are not followed by another condition.
- Explicit media-type queries such as `screen or (hover)` now fail instead of being serialized as if `or` were part of the media type prelude.
- Existing valid forms remain supported, including `not screen`, `only screen`, `not (color) or (hover)`, `screen and not (color)`, and parenthesized `or` groups after `screen and`.
- `wordpress-media-layer-minifier.php --self-test` now covers a dangling boolean operator inside a layered block-theme media rule.

## Verification

- Red-first focused: `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` failed with `1 test files, 175 assertions, 2 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` passed with `1 test files, 190 assertions, 0 failures`.
- Adjacent focused: `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed with `2 test files, 666 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` passed with `13 test files, 3152 assertions, 0 failures`.
- Example: `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test` exited `0`.
- Syntax: `php -l` passed for `MediaQueryParser.php`, `MediaQueryParserTest.php`, and `wordpress-media-layer-minifier.php`.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `3141 -> 3152 pass / 0 fail`.
- Conservative mapped coverage remains `1696 / 3532`.

## Non-Overlap

This avoids accepted media range fallback, typed/unknown/equality ranges, calc spacing, all-media elision, explicit media-type top-level OR guards already covered for `screen and (...) or (...)`, x-resolution units, compound resolution prefixing, double-negation lowering, bundler media-type conjunction, CSS Modules, CSSOM, SourceMap, target prefixing, custom at-rule visitor, and property-value clusters.

## Dependency Closure

No new support component is needed. The slice reuses the native `MediaQueryParser`, `CssMinifier`, `TransitionPrefixer`, focused PHP tests, and lane-local example smoke. No Node, Rust, browser, WASM runtime, parser generator, or external service is required.

## Next Task

Continue non-overlapping media-query work around remaining parser recovery, target-boundary serialization, or import/bundler behavior not covered by the accepted range/layer validation clusters.
