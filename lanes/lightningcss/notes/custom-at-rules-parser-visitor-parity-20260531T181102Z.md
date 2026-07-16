# LightningCSS Custom At-Rules Parser Visitor Parity - 2026-05-31 18:11 UTC

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260531T181102Z`
- Base accepted HEAD: `cd6f2b625904737fc04b396f74c3b6c78b20c742`
- Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `node/test/visitor.test.mjs` `100vh fix` returns a `supports` rule from a `Rule.style` visitor and expects `@supports (-webkit-touch-callout:none)` wrapping a cloned style rule with `height:-webkit-fill-available`.
- `node/ast.d.ts` exposes `Rule` variants with `type: "supports"` and `SupportsRule` / `SupportsCondition` declaration shapes, so custom at-rule visitor replacements should be able to emit returned supports-rule objects just like returned media/style objects.

## Implementation

- `CustomAtRuleTransformer` now serializes returned `type => supports` rules from regular replacement emission and from `Rule.style` visitor replacement lists.
- Supports declaration conditions serialize from upstream-like `propertyId` / `value` shapes.
- Returned visitor values with `type => stretch` and `vendorPrefix => ["webkit"]` serialize as `-webkit-fill-available`.
- `wordpress-custom-at-rules-transformer.php` now exercises the same returned supports-rule path for a block viewport fallback.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 75 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2885 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  - Result: `OK`
- `git diff --check -- lanes/lightningcss`
  - Result: no whitespace errors

## Status Delta

- Focused custom at-rule assertions: `71 -> 75`.
- Full LightningCSS lane assertions/pass count: `2881 -> 2885`.
- Conservative mapped coverage: `1637 -> 1638 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the existing native `CustomAtRuleTransformer`, declaration parser, visitor value serializer, and CSS minifier. No upstream binary, browser service, parser generator, or external CSS engine is required.

## Non-Overlap

This avoids the accepted native `Rule.media` visitor, selector visitor, declaration visitor, function/env/var visitor, returned media/style rule, bundle/source-map, CSS Modules, linear-gradient, object-fit, and CSSOM property-location clusters. The slice is limited to returned `@supports` rule objects and the associated `stretch` value needed by the upstream visitor fixture.
