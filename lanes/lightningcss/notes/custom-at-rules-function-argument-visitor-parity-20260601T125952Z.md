# Custom At-Rule Function Argument Visitor Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T125952Z`

Base accepted HEAD: `27cf721c25e91c9dcac0b599677df25582e922d2`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream files: `napi/src/at_rule_parser.rs`, `src/values/syntax.rs`, and `src/properties/custom.rs`.
- Behavior: custom at-rule preludes using `SyntaxString` `*` parse to a token list. A generic function in that token list owns an argument token list, so value/token visitors for children such as lengths, dashed idents, and ident tokens run before the custom `Rule` visitor observes the final prelude.

## Red-First Evidence

Before the implementation change, this focused test failed:

```bash
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
```

Failure: `custom at-rules visit upstream generic function prelude arguments before custom rule visitors`.

Observed events were only:

```text
length:16px
rule:theme(1rem,--wp-gap,draft)
```

The native PHP path reached the numeric length through the legacy function-exit parser fallback but skipped the dashed ident and token ident arguments, so the custom `Rule` visitor received a partially visited prelude.

## Implementation

- `CustomAtRuleTransformer::visitCustomPreludeTokenListComponent()` now traverses parsed generic function arguments in universal custom prelude token lists when there is no `FunctionExit` visitor for that function.
- The traversal reuses the existing custom prelude token-list visitor, so `Length`, `DashedIdent`, and `Token` replacements update the function node before custom `Rule` visitors run.
- Existing `FunctionExit` ordering is preserved for accepted percentage/number token tests that intentionally observe unvisited token arguments before returning a replacement.
- Added a WordPress smoke for `@wp-design-token theme(16px,--wp-gap,draft)` that emits a `:root` custom property from the visited custom prelude.

## Verification

- Red-first focused run before implementation failed as expected: `1 test files, 409 assertions, 1 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed: `1 test files, 417 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-function-arguments.php --self-test` passed: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 7937 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-function-arguments.php` passed.
- `git diff --check -- lanes/lightningcss` passed.

## Status Delta

- Focused custom at-rule assertions: `408 -> 417` (`+9`) for this test file.
- Full LightningCSS lane assertions: `7927 -> 7937` (`+10`) in this worktree.
- `lane-status.json` `phpPass`: `7927 -> 7937`.
- Conservative mapped coverage remains `2392 / 3532`; this deepens the represented custom-at-rule parser/visitor cluster instead of adding a new upstream denominator row.
- Full upstream Rust/Node/WASM runners were not executed in this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP custom at-rule transformer, component-value parser, value visitor dispatch, minifier, and serializer.

## Non-Overlap

This does not repeat accepted custom at-rule declaration-list/style-block parser coverage, top-level Function visitor replacement, FunctionExit percentage/number token behavior, comma delimiter token-list parsing, nested block token-list traversal, image/unit/ratio/transform typed prelude traversal, returned media/rule visitor traversal, or stylesheet enter/exit visitor parity. It is scoped to visitor traversal for generic function arguments inside universal custom at-rule preludes.

## Next Task

Continue custom at-rule parser/visitor parity around space-separated raw token lists inside generic function arguments and returned parser bodies that combine declaration and rule visitors.
