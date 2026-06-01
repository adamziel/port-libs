## Custom At-Rule Attribute Selector Parser Visitor Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T135551Z`

Source truth:
- Pinned upstream manifest commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream API surface: `node/ast.d.ts` `SelectorComponent` includes `type: "attribute"` with `operation.operator`, `operation.value`, and optional `caseSensitivity`; `AttrSelectorOperator` includes `equal`, `includes`, `dash-match`, `prefix`, `substring`, and `suffix`.
- This deepens the already represented Node visitor/custom parser cluster rather than increasing conservative mapped denominator coverage.

Implemented behavior:
- `CustomAtRuleTransformer` now parses attribute selectors into upstream-style visitor components instead of raw punctuation/type-token fragments.
- Attribute selector serialization now supports upstream operator names, CSS operator tokens, optional namespace constraints, and `i` / `s` case-sensitivity flags.
- Custom at-rule `rule-list` body rules and ordinary source `Selector` visitors both expose attribute selector AST components before visitor replacements.

WordPress scenario:
- `wordpress-custom-at-rule-attribute-selector-parser.php` models a build-free block variant transform that rewrites `[data-state="draft" i]` selectors in a custom at-rule body and rewrites a source `[data-state="published" s]` selector through the `Selector` visitor without Node/WASM.

Verification:
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-attribute-selector-parser.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 427 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-attribute-selector-parser.php --self-test` -> `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8154 assertions, 0 failures`

Dependency closure:
- No new support component is needed. This reuses the existing native `CustomAtRuleTransformer` selector parsing and visitor serialization path.

Non-overlap:
- Avoids the accepted custom at-rule SyntaxString token-list, pseudo-element selector, returned declaration-list, returned media exit, token-array, nested returned-body, and function/env/var visitor clusters. This slice is limited to attribute selector AST parity in parser bodies and source `Selector` visitors.
