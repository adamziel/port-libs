# Custom At-Rule Unicode Name And Token-List Visitor Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T100944Z`

Base accepted HEAD: `2648b52fcffdb6ca0b8fcb1ff66f6b03786869e2`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `napi/src/at_rule_parser.rs::CustomAtRuleParser::parse_prelude()` receives the cssparser-decoded at-rule name as `CowRcStr` and looks it up in `CustomAtRuleParser.configs`.
- `napi/src/at_rule_parser.rs::AtRule::to_css()` serializes custom at-rule names with `serialize_identifier`.
- `src/values/syntax.rs::SyntaxString::Universal` parses a raw `TokenList`, whose tokens are visited by the normal visitor pipeline.

## Behavior

Native PHP now uses the same CSS escape-aware identifier scanner for custom at-rule parser lookup and universal `*` prelude token lists:

- Literal Unicode custom at-rule names such as `@thème` resolve to the configured custom parser and visitor callback.
- Escaped Unicode custom at-rule names such as `@th\00e8 me` decode before parser lookup and preserve the consumed escape whitespace boundary.
- Universal prelude token lists decode non-ASCII identifiers, dashed identifiers, and escaped at-keywords before `Token` / `DashedIdent` visitors run.
- Declaration-value token visitors share the same decoded identifier scanner, preserving raw spelling when visitors return `null`.

The WordPress smoke now models localized block custom at-rules and localized design-token aliases without Node/WASM.

## Red-First Evidence

After adding focused tests and before the source patch:

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
FAIL custom at-rules decode upstream unicode at-rule names before parser lookup
Invalid CSS at-rule prelude: @th\00e8 me badge
FAIL custom at-rules visit upstream unicode universal prelude identifiers before custom rule visitors
1 test files, 367 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 369 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7312 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-custom-at-rule-unicode-ident-visitor.php --self-test
OK

php -l lanes/lightningcss/src/CustomAtRuleTransformer.php
No syntax errors detected in lanes/lightningcss/src/CustomAtRuleTransformer.php

php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
No syntax errors detected in lanes/lightningcss/tests/CustomAtRuleTransformerTest.php

php -l lanes/lightningcss/examples/wordpress-custom-at-rule-unicode-ident-visitor.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-custom-at-rule-unicode-ident-visitor.php
```

## Status

- Focused assertion delta in `CustomAtRuleTransformerTest.php`: `+4` (`365 -> 369`).
- Full LightningCSS lane assertion delta: `+4` (`7308 -> 7312`).
- Conservative mapped coverage remains `2365 / 3532`; this deepens the already represented custom at-rule parser/visitor cluster.
- Dependency closure: no new support component needed; reused the native PHP custom at-rule scanner, CSS escape decoder, SyntaxString/token-list parser, visitor dispatch, and WordPress example harness.
- Non-overlap: avoids the accepted Unicode SyntaxString identifier validation, escaped ASCII at-rule name lookup, token-list component traversal, token-array re-entry, env/var, RuleExit, selector, and source-location custom at-rule slices. This patch is scoped to Unicode/escaped at-rule names and universal token-list identifier tokenization before visitors.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.
