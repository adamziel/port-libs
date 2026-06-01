# Custom At-Rule Unicode Identifier Parser Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T094126Z`

Accepted base: `9495523910adeabd01c9bc2c77431af9d8027200`

## Source Truth

- Upstream pinned checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `src/values/syntax.rs::is_ident_start` accepts ASCII letters, `_`, and code points `>= U+0080` for SyntaxString literals.
- `src/values/syntax.rs::is_name_code_point` allows non-ASCII continuation code points in literals.
- `src/values/ident.rs::CustomIdent::parse` accepts CSS identifiers but rejects `initial`, `inherit`, `unset`, `default`, `revert`, and `revert-layer`.
- `src/values/ident.rs::DashedIdent::parse` accepts any CSS identifier whose decoded value starts with `--`.

## Patch

`CustomAtRuleTransformer` now uses a shared CSS identifier validator for custom at-rule SyntaxString identifiers. This preserves existing ASCII behavior while adding upstream-compatible non-ASCII literal, `<custom-ident>`, and `<dashed-ident>` prelude parsing and visitor replacements. The same validator now rejects `default` for `<custom-ident>` and validates dashed-ident visitor replacements beyond a simple `--` prefix.

The focused test covers:

- `@slot café;` parsed as `<custom-ident>`, visited by `CustomIdent`, and exposed to `Rule.custom` as `wp-café`.
- `@tokens --wp-échelle --wp-accent;` parsed as repeated `<dashed-ident>+`, visited by `DashedIdent`, and exposed as rewritten AST components.
- `@mode édition édition;` parsed through a repeated non-ASCII literal SyntaxString.
- `@slot default;` rejected for `<custom-ident>` parity with upstream `CustomIdent::parse`.

The WordPress smoke models localized block style names and design-token custom at-rules without Node/WASM.

## Verification

Red-first check after adding the focused test:

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
FAIL custom at-rules parse upstream unicode SyntaxString identifiers before visitors
Invalid custom at-rule prelude for <custom-ident>: café
1 test files, 350 assertions, 1 failures
```

Passing focused check after implementation:

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 359 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-custom-at-rule-unicode-ident-visitor.php --self-test
OK
```

Final hygiene:

```text
php -l lanes/lightningcss/src/CustomAtRuleTransformer.php
No syntax errors detected in lanes/lightningcss/src/CustomAtRuleTransformer.php

php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
No syntax errors detected in lanes/lightningcss/tests/CustomAtRuleTransformerTest.php

php -l lanes/lightningcss/examples/wordpress-custom-at-rule-unicode-ident-visitor.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-custom-at-rule-unicode-ident-visitor.php

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7216 assertions, 0 failures

git diff --check -- lanes/lightningcss
OK
```

## Status

- Focused assertion delta in `CustomAtRuleTransformerTest.php`: `+9` (`350 -> 359`).
- Conservative mapped coverage remains `2365 / 3532`; this deepens the already represented custom at-rule parser/visitor cluster.
- Dependency closure: no new support component needed; reused the native PHP SyntaxString parser, custom/dashed identifier visitors, and existing CSS escape decoding.
- Non-overlap: avoids accepted custom at-rule image, ratio, length-percentage, token-list, token-array, nested body, style-sheet enter, pseudo-element selector, env/var, and source-location visitor slices; this patch is limited to Unicode identifier parsing and validation.
- Next task: target a distinct custom at-rule parser/visitor gap such as nested parser recovery or source-location behavior; do not repeat Unicode SyntaxString identifier validation.
- Full upstream Rust/Node/WASM runners: not run for isolated micro-slice.
