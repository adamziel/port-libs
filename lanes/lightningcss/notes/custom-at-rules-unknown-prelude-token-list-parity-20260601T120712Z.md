# Custom At-Rule Returned Prelude Token-List Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T120712Z`

Base accepted HEAD: `104a9f5fce0ab0f0e77688b3f9277242f2f9e31c`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream files inspected: `node/index.d.ts`, `node/ast.d.ts`, `napi/src/at_rule_parser.rs`, `napi/src/transformer.rs`, `node/test/customAtRules.mjs`, and `node/test/visitor.test.mjs`.
- Behavior: upstream visitor rule objects expose unknown/custom at-rule preludes as component token lists and serialize returned rule replacements through Lightning CSS' AST printer.

## Red-First Evidence

Before this patch, a visitor returning an upstream-shaped unknown rule replacement with `prelude` set to a token-list array dropped the replacement prelude:

```css
@wp-token --wp-gap;
```

with a returned `prelude` token list for `--wp-card-gap` emitted:

```css
@wp-token;
```

The emitter only accepted string preludes and fell back to stale `preludeText` for arrays.

## Implementation

- Added a shared at-rule prelude serializer that accepts strings, token-list arrays, and single token/value nodes.
- Wired that serializer into returned unknown at-rules and configured custom at-rules.
- Added focused assertions for returned unknown and custom at-rule replacements with upstream-shaped token-list preludes.
- Added a WordPress smoke covering token/layer at-rules returned from PHP visitors without Node/WASM at runtime.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-prelude-token-list-return.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed: `1 test files, 401 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-prelude-token-list-return.php --self-test` passed: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 7702 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.

## Status Delta

- Focused custom at-rule assertions: `393 -> 401` (`+8`) for this micro-slice.
- `lane-status.json` `phpPass`: `7694 -> 7702`, matching the current full lane test output in this worktree.
- Conservative mapped coverage remains `2374 / 3532`; this deepens the already represented upstream custom at-rule/visitor token-list cluster rather than adding a new denominator row.
- Full upstream Rust/Node/WASM runners were not executed in this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP custom at-rule transformer, component-value serializer, minifier, and existing visitor replacement pipeline.

## Non-Overlap

This does not repeat accepted custom at-rule token-list exposure, comma-delimiter parsing, SyntaxString parsing, returned media exit traversal, stylesheet visitors, import media range tails, CSSOM view-transition read/write, or property-value fallback work. It is scoped to serializing visitor-returned prelude token lists for unknown and configured custom at-rules.

## Next Task

Continue custom at-rule parser/visitor parity around remaining parser-body re-entry and unknown-rule body token serialization gaps, rather than adding another prelude exposure-only test.
