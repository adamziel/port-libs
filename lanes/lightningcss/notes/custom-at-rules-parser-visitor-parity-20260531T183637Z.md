# LightningCSS Custom At-Rules Parser Visitor Parity - 2026-05-31 18:36 UTC

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260531T183637Z`
- Base accepted HEAD: `1d7de15e4e85a2b8dbfd1c80922d2921091d0371`
- Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `node/test/visitor.test.mjs` `custom units` visits `Token.dimension` for `3--step`.
- The upstream visitor returns a `function` value for `calc` whose arguments are a `token` number, `token` delim `*`, and a `var` value.
- `node/ast.d.ts` defines `Token.dimension` with `value` and `unit`, plus `TokenOrValue` function, token, and variable shapes.

## Implementation

- `CustomAtRuleTransformer::composeVisitors()` now forwards named `Token.dimension` visitors.
- Declaration value rewriting now detects custom-unit dimensions such as `3--step` and calls token visitors with upstream-shaped `type`, `value`, `unit`, and `raw` fields.
- Returned token values now serialize `number`, `percentage`, `dimension`, `delim`, and `at-keyword` token forms.
- Returned `calc()` function token streams serialize without comma separators, matching the upstream custom-unit fixture.
- `wordpress-custom-at-rules-transformer.php` now declares `@unit --wp-fluid-step`, rewrites `font-size: 3--wp-fluid-step`, and checks the collected unit in self-test mode.

## Verification

- Baseline focused custom at-rule run before this slice:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 77 assertions, 0 failures`
- Focused after fix:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 81 assertions, 0 failures`
- Full LightningCSS lane:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 3064 assertions, 0 failures`
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  - Result: `OK`

## Status Delta

- Focused custom at-rule assertions: `77 -> 81`.
- Full LightningCSS lane assertions/pass count: `3060 -> 3064`.
- Conservative mapped coverage: `1684 -> 1685 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the existing native `CustomAtRuleTransformer`, declaration parser, minifier, and visitor serializer. No upstream binary, browser service, parser generator, or external CSS engine is required.

## Non-Overlap

This avoids the accepted custom at-rule declaration-list, mixin, returned media/style/supports, unknown/token at-keyword, visitor factory dependency, selector, URL, EnvironmentVariable, Variable, FunctionExit, Length, Declaration, StyleSheet, native media visitor, CSS Modules, source-map, bundler, CSSOM, and target-prefix clusters. The slice is limited to `Token.dimension` custom-unit visitor parsing and returned calc token serialization.
