# Custom At-Rule Variable Fallback Prelude Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T154521Z`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `napi/src/at_rule_parser.rs::AtRule::visit_children()` visits parsed custom at-rule preludes before custom rule visitors.
- `napi/src/transformer.rs::visit_token_list()` visits `TokenOrValue::Var` and `TokenOrValue::Env` enter visitors, then child token lists, then exit visitors.
- `src/properties/custom.rs` derives visitor traversal for `Variable` and `EnvironmentVariable`, whose `fallback` fields are `TokenList` children.

## Red-First Evidence

A local PHP probe before this patch transformed:

```css
@wp-token var(--card-gap, 16px draft);
@wp-safe env(--safe-gap 1, 8px draft);
```

with `Length`, `Token.ident`, `VariableExit`, `EnvironmentVariableExit`, and `Rule.custom` visitors. It produced only `var-exit:raw`, `env-exit:raw`, and raw `Rule.custom` preludes, proving fallback token lists were not traversed before exit/custom-rule visitors.

## Implementation

- Split custom-prelude `var()` and `env()` traversal from the generic structured-value helper so prelude variables can run enter visitors, fallback child visitors, and exit visitors in upstream order.
- Parse raw fallback token-list payloads on the prelude traversal path, then re-enter the existing custom-prelude token visitor pipeline.
- Serialize variable and environment-variable fallbacks as token lists, preserving spaces between fallback components instead of treating each fallback component as a comma argument.
- Added focused PHP coverage for `var(--card-gap, 16px draft)` and `env(--safe-gap 1, 8px draft)` inside universal custom at-rule preludes.
- Added `wordpress-custom-at-rule-var-env-fallback-prelude.php` as a self-testing design-token smoke.

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 464 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-custom-at-rule-var-env-fallback-prelude.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 8513 assertions, 0 failures
```

Final hygiene:

```text
php -l lanes/lightningcss/src/CustomAtRuleTransformer.php
php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
php -l lanes/lightningcss/examples/wordpress-custom-at-rule-var-env-fallback-prelude.php
php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status OK\n";'
git diff --check -- lanes/lightningcss
```

## Status

- Focused `CustomAtRuleTransformerTest.php` moved from `451` to `464` assertions.
- Full LightningCSS PHP lane moved from `8500` to `8513` assertions, `0` failures.
- Conservative mapped coverage remains `2398 / 3532`; this deepens the already represented custom at-rule parser/visitor cluster rather than claiming a new denominator row.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP component-value parser, custom prelude token-list traversal, visitor dispatch, and serializer paths.

## Non-Overlap

This does not repeat accepted custom at-rule declaration-list/mixin/rule-list parsing, escaped names, token-list component preludes, generic function arguments, FunctionExit space-separated arguments, env/var visitor replacement in declaration values, returned rule traversal, attribute-selector parser bodies, or media/supports/container rule visitors. The change is scoped to fallback token-list child traversal for `var()` and `env()` nodes inside custom at-rule universal preludes.
