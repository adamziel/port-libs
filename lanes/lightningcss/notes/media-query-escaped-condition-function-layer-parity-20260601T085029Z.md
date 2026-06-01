# LightningCSS Media Query Escaped Condition Function Layer Parity - 2026-06-01

Slice: `lightningcss-media-query-range-layer-parity-20260601T085029Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source: `src/media_query.rs` `parse_query_condition`, `parse_parens_or_function`, and media feature parsing. Upstream parses escaped identifier sequences before classifying a condition token, so escaped unsupported condition functions such as `unk\6e own(foo)` are still rejected.
- Local pinned native-addon probe using `lightningcss.linux-x64-gnu.node` confirmed both layered cases return `Invalid media query`:
  - `@layer blocks { @media (unk\6e own(foo)) { ... } }`
  - `@layer blocks { @media (width >= 240px) and (unk\6e own(foo)) { ... } }`

## Red-First Evidence

Before this patch, the PHP minifier accepted escaped condition-function tokens inside layered media conditions and emitted them as if they were boolean features:

```text
@layer blocks{@media (unk\6eown(foo)){.foo{color:#ff0}}}
@layer blocks{@media (width>=240px) and (unk\6eown(foo)){.foo{color:#ff0}}}
```

The pinned upstream native addon rejected both as invalid media queries.

## Native Delta

- `MediaQueryParser` now detects CSS function tokens with escaped identifier names before treating a parenthesized media operand as a feature.
- The decoded function name is used in diagnostics, so `unk\6e own(foo)` is classified as unsupported `unknown(foo)`.
- Layered `@media` minification now rejects escaped unsupported condition functions both as the whole condition and when combined with valid range conditions.
- `wordpress-media-layer-minifier.php --self-test` now guards escaped unsupported condition functions in WordPress block-theme layer CSS.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 1641 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test`
  - Result: exited `0`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 7003 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss`
  - Result: clean.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moved from `6998` to `7003` assertions with `0` failures.
- Conservative mapped coverage remains `2360 / 3532`; this deepens the already represented media-query parser/layer validation cluster rather than claiming a new upstream inventory row.

## Non-Overlap

This does not repeat accepted media range target fallbacks, typed/unknown/equality ranges, unitless/scientific/math/env values, resolution prefixing, x/dppx serialization, explicit media-type validation, boolean wrapper flattening, custom-media expansion, bundle/import graph, CSS Modules, source maps, CSSOM, target-prefixing, or property-value slices. The behavior here is only escaped unsupported condition-function rejection in media query/layer parsing.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `MediaQueryParser`, `CssMinifier`, existing focused tests, and the lane-local WordPress media-layer example. No Node/Rust/WASM dependency is required for runtime behavior.

## Next Task

Continue with non-overlapping LightningCSS media-query parser recovery/serialization, bundle/import graph, SourceMap, CSS Modules, CSSOM, custom-at-rule, target-prefixing, and property-value parity.
