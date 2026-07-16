# Custom At-Rule Identifier Prelude Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T003157Z`

Accepted base: `5b87111468b46af8cd72097f10d11bf759b0ca92`

## Source Truth

- Upstream pinned checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `napi/src/at_rule_parser.rs:204-211` visits a custom at-rule prelude before the custom body.
- `src/values/ident.rs:25` wires `CustomIdent` to `visit_custom_ident`.
- `src/values/ident.rs:148` wires `DashedIdent` to `visit_dashed_ident`.
- `napi/src/transformer.rs:503-514` exposes both identifier visitors to JavaScript visitors.

## Patch

`CustomAtRuleTransformer::visitCustomPreludeValue()` now dispatches parsed `custom-ident` and `dashed-ident` SyntaxString prelude nodes through the existing `applyCustomIdentVisitor()` and `applyDashedIdentVisitor()` helpers. This keeps identifier validation centralized and updates both serialized `prelude` and structured `preludeAst` before `Rule.custom` visitors run.

The new focused test covers:

- `@slot hero;` with `<custom-ident>` rewritten by `CustomIdent` to `wp-hero`.
- `@tokens --accent --spacing;` with repeated `<dashed-ident>+` rewritten by `DashedIdent`.
- `Rule.custom` receiving the rewritten serialized prelude and rewritten AST.

The WordPress example uses the same path for block style slot and design-token custom at-rules.

## Verification

Red-first check before implementation:

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 186 assertions, 1 failures
FAIL custom at-rules visit upstream identifier preludes before custom rule visitors
Expected: ['hero']
Actual: []
```

Passing checks after implementation:

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 192 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 5143 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-custom-at-rule-prelude-identifier-visitor.php --self-test
OK
```

Final local hygiene:

```text
php -l lanes/lightningcss/src/CustomAtRuleTransformer.php
No syntax errors detected in lanes/lightningcss/src/CustomAtRuleTransformer.php

php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
No syntax errors detected in lanes/lightningcss/tests/CustomAtRuleTransformerTest.php

php -l lanes/lightningcss/examples/wordpress-custom-at-rule-prelude-identifier-visitor.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-custom-at-rule-prelude-identifier-visitor.php

php -r 'json_decode(...)'
OK

git diff --check -- lanes/lightningcss
OK
```

## Status

- Focused PHP assertion delta: `+8` full-lane assertions (`5135 -> 5143`).
- Mapped denominator: unchanged at `2238 / 3532`; this deepens the accepted custom at-rule parser/visitor cluster rather than adding a new upstream denominator row.
- Dependency closure: no new support component needed; reused existing native PHP SyntaxString parser, identifier visitor normalization, and custom at-rule visitor pipeline.
- Rework-note check: the existing `port-lightningcss-current-rebase-20260525T053931Z-02383337.needs-lane-rework.md` note is about stale custom-media import-tail conflicts and does not overlap this custom at-rule parser/visitor slice.
- Full upstream Rust/Node/WASM runners: not run for isolated micro-slice.
