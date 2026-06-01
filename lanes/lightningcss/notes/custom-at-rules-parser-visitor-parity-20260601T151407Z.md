# Custom At-Rule FunctionExit Prelude Argument Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T151407Z`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `napi/src/at_rule_parser.rs::AtRule::visit_children()` visits a parsed custom at-rule prelude before the custom rule visitor observes it.
- `napi/src/transformer.rs::visit_token_list()` uses the normal visitor list traversal for `TokenOrValue::Function`, where children are visited before the exit-stage `FunctionExit` visitor.
- `src/values/syntax.rs::SyntaxString::Universal` parses custom prelude `*` values as a `TokenList`, so generic function arguments in custom preludes must use the same child-before-exit visitor ordering.

## Implementation

- `CustomAtRuleTransformer` now visits children of space-separated generic function arguments in universal custom-at-rule preludes before invoking `FunctionExit`.
- The `FunctionExit` callback receives the mutated argument AST and the existing `argumentSeparator: "space"` marker, matching the upstream traversal shape.
- Existing comma/default function-exit behavior is preserved to avoid changing already accepted token replacement ordering for percentage and number function-exit tests.
- Added focused coverage for `theme(16px --wp-gap draft)` where `Length`, `DashedIdent`, and `Token.ident` visitors run before `FunctionExit`, and the custom rule visitor observes `theme(1rem --theme-wp-gap live)`.
- Added `wordpress-custom-at-rule-space-function-exit.php` as a WordPress block-token smoke without Node/WASM.

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 451 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-custom-at-rule-space-function-exit.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 8408 assertions, 0 failures
```

Final hygiene was run after source, test, example, note, and status edits:

```text
php -l lanes/lightningcss/src/CustomAtRuleTransformer.php
php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
php -l lanes/lightningcss/examples/wordpress-custom-at-rule-space-function-exit.php
php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status OK\n";'
git diff --check -- lanes/lightningcss
```

## Status

- Focused `CustomAtRuleTransformerTest.php` evidence is now `451 assertions / 0 failures`.
- Full LightningCSS PHP lane evidence is now `13 files / 8408 assertions / 0 failures`; `lane-status.json` `phpPass` is updated to `8408`.
- Conservative mapped coverage remains `2393 / 3532`; this deepens the already represented custom at-rule parser/visitor cluster rather than claiming a new denominator row.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP custom at-rule transformer, component-value parser, visitor dispatch, and serializer paths.

## Non-Overlap

This does not repeat accepted custom at-rule declaration-list/mixin/rule-list parser behavior, escaped name/prelude handling, token-list component parsing, comma-separated generic function argument visitors, raw env/var spacing, token-array replacement re-entry, attribute selector parser coverage, or known media/supports/container rule visitors. The change is scoped to space-separated generic function argument traversal before `FunctionExit`.
