# Custom At-Rule Comma Token-List Prelude Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T055811Z`

Base accepted HEAD: `7db0bee1b6d6b17fcc1ae3a0e1b10ac7a87ade2d`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream files: `napi/src/at_rule_parser.rs`, `node/composeVisitors.js`, and `node/index.d.ts`.
- Behavior: custom at-rule preludes using `SyntaxString` `*` are parsed component values. The prelude AST is visited before the custom `Rule` visitor, and composed Function, Variable, Token, and EnvironmentVariable visitors continue across delimiter tokens such as top-level commas.

## Red-First Evidence

Before this patch, a universal custom prelude such as:

```css
@plugin theme("card-gap"), var(--wp-gap), @--wp-accent, env(--wp-breakpoint);
```

was split only on whitespace. The comma stayed attached to the preceding or following token, so the native PHP visitor path did not visit the later `var()`, at-keyword, or `env()` components as separate upstream-like component values.

## Implementation

- `CustomAtRuleTransformer::parseComponentValueList()` now tokenizes top-level comma delimiters while preserving commas inside strings, functions, and bracketed segments.
- Comma delimiters are represented as parsed token components, matching the existing Token AST shape.
- Parsed component-value sequence serialization now preserves normal space separation while compacting comma delimiters to upstream-style `a,b,c` output.
- Added a WordPress smoke that turns a comma-separated custom prelude into a `:root` custom property through Function, Variable, Token, EnvironmentVariable, and Rule visitors.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-comma-token-list-prelude.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` passed: `1 test files, 296 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-comma-token-list-prelude.php --self-test` passed: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 6328 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.

## Status Delta

- Focused custom at-rule assertions: `290 -> 296` (`+6`) for this micro-slice.
- `lane-status.json` `phpPass`: `6322 -> 6328`, matching the current full lane test output in this worktree.
- Conservative mapped coverage remains `2359 / 3532`; this deepens the already represented custom at-rule parser/visitor cluster rather than adding a new upstream manifest denominator row.
- Full upstream Rust/Node/WASM runners were not executed in this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP custom at-rule transformer, parsed component-value visitor dispatch, and existing minifier/serializer pipeline.

## Non-Overlap

This does not repeat accepted custom at-rule SyntaxString component coverage, token-list prelude visitor coverage, token-array replacement re-entry, transform/ratio/length-percentage prelude visitors, raw env spacing, returned rule traversal, or stylesheet enter/exit visitor parity. It is scoped to top-level comma delimiter tokenization inside universal `*` custom at-rule preludes.

## Next Task

Continue custom at-rule parser/visitor parity around remaining upstream `SyntaxString` component forms and parser-level visitor re-entry cases, especially delimiter-heavy token lists and returned parsed bodies that combine declaration and rule visitors.
