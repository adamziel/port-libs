# Custom At-Rule Space Function Argument Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T141346Z`

## Source Truth

- Upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `napi/src/at_rule_parser.rs` parses custom at-rule preludes with `SyntaxString::parse_value` and visits the parsed prelude before custom rule visitors.
- `src/values/syntax.rs` maps universal `*` syntax to a custom-property `TokenList`.
- `node/ast.d.ts` models generic `Function.arguments` as `TokenOrValue[]`, so a universal-prelude function argument such as `theme(16px --wp-gap draft)` must expose its inner length, dashed-ident, and ident token visitors before `Rule.custom`.

## Red-First Evidence

Before this patch, a local PHP probe for:

```css
@wp-design-token theme(16px --wp-gap draft);
.wp-block-card { color: red; }
```

with `Length`, `DashedIdent`, `Token.ident`, and `Rule.custom` visitors produced `rule:theme(16px --wp-gap draft)` without visiting the function's `16px`, `--wp-gap`, or `draft` argument tokens. The generic token visitor only saw the later declaration value `red`, proving the prelude function argument was opaque.

## Implementation

- Added generic function argument parsing that preserves the existing comma-separated behavior by default.
- When a single generic function argument parses into multiple top-level component values, it is stored as a structured token list with `argumentSeparator: "space"`.
- Updated function serialization helpers to round-trip that separator, so visitor-mutated arguments serialize as `theme(1rem --theme-wp-gap live)` instead of comma-joining the values.
- Added focused PHP coverage for two space-separated function arguments in one universal custom at-rule prelude.
- Added `wordpress-custom-at-rule-space-function-arguments.php` as a self-testing WordPress design-token smoke.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 442 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-space-function-arguments.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8199 assertions, 0 failures`
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-space-function-arguments.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Status Delta

- `phpPass`: `8173 -> 8199`
- `phpFail`: `0`
- Mapped coverage remains `2393 / 3532`; this deepens an already represented custom at-rule parser/visitor cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP component-value parser, visitor dispatch, and serializer paths.

## Non-Overlap And Follow-Up

This does not repeat the already accepted comma-separated generic function argument visitor coverage, returned declaration-list parser body traversal, escaped custom at-rule names, selector visitor body coverage, or CSSOM/font longhand slices.

Suggested next custom at-rule follow-up: FunctionExit parity for space-separated generic function arguments, or returned parser bodies that combine declaration and rule visitors in one custom rule.
