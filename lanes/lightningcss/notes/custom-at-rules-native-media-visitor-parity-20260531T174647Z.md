# Custom At-Rules Native Media Visitor Parity 2026-05-31T17:46Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream cases:
  - `node/test/visitor.test.mjs` `hover media query`
  - `node/test/visitor.test.mjs` `dark theme class`

## Implementation

- `CustomAtRuleTransformer` now exposes native `@media` rules to `Rule.media` visitors as returned-rule AST objects.
- Media visitor ASTs include a bounded `query.mediaQueries` shape for boolean, plain, and simple range feature conditions plus parsed nested returned rules.
- `composeVisitors()` now composes `Rule.media` callbacks, forwarding returned media/style rule arrays through later visitors.
- Returned selector serialization now supports attribute selector components inside returned-rule and functional pseudo-class selectors.
- `CssMinifier` now preserves selector descendant spaces after attribute selectors while leaving grid line-name declaration compaction intact.
- The WordPress custom at-rule smoke now rewrites a hover media block into a `.wp-hoverable` selector using the native media visitor path.

## Verification

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 65 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 71 assertions, 0 failures`.
- Minifier focused check for the selector spacing helper:
  `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  => `1 test files, 1050 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 2800 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  exits `0`.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.

## Counting

- PHP assertion delta: `+6` focused assertions, from `2794` to `2800`.
- Conservative mapped coverage delta: `+2`, from `1601 / 3532` to `1603 / 3532`.
- Counted checks: native `Rule.media` boolean hover visitor replacement and native `Rule.media` plain `prefers-color-scheme` visitor cloning.

## Non-Overlap

This slice does not repeat accepted custom at-rule declaration-list/mixin/rule-list parser coverage, returned custom media objects, composed custom/unknown/token/function visitors, FunctionExit/Length chaining, environment-variable/variable visitors, style-rule visitor composition, visitor factory dependencies, selector prefix/nth-of-S visitor coverage, or the accepted media-query minifier/range fallback clusters. It only adds native `@media` rule visitor traversal and returned-rule serialization needed by the upstream visitor cases.

## Dependency Closure

No new support component is needed. The patch reuses the bounded native `CustomAtRuleTransformer`, `MediaQueryParser`, `DeclarationBlock`, `CssMinifier`, selector scanner, and returned-rule serializers. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is introduced.

Root harness status: not run - isolated micro-slice.
