# LightningCSS Custom At-Rule Literal Repetition Parser Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T050944Z`

Source truth:
- Upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/values/syntax.rs` parses `SyntaxString` multipliers for literal components with `+` as whitespace-separated repetition and `#` as comma-separated repetition.
- `node/index.d.ts` exposes parsed custom at-rule preludes to `Rule.custom` visitors before replacement or removal.

Behavior ported:
- `CustomAtRuleTransformer` now accepts literal syntax components with multipliers such as `compact+` and `preview#`.
- Matching preludes produce the same repeated AST shape already used for repeated typed components: `type: repeated`, literal child components, and `multiplier.type` of `space` or `comma`.
- Invalid mixed literal sequences still fail parser admission before visitor replacement.

Red-first evidence:
- Before this patch, `@mode compact compact;` with custom definition `prelude => compact+` failed with `InvalidArgumentException: Invalid custom at-rule prelude for compact+: compact compact`.

Focused verification:
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-literal-repetition-visitor.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`: 1 test file, 282 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-literal-repetition-visitor.php --self-test`: OK.
- `git diff --check -- lanes/lightningcss`: passed with no output.

Dependency closure:
- No new support component is needed. This reuses the existing native `CustomAtRuleTransformer` SyntaxString parser and visitor pipeline.

Non-overlap:
- This does not repeat the accepted custom at-rule body traversal, token-array visitor replacement, function visitor traversal, nested returned body parsing, image/selector/unit typed prelude parsing, or generic repeated typed-component SyntaxString coverage. It only covers repeated literal SyntaxString components.
