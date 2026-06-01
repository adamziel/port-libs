# Custom At-Rule Escaped Name Parser/Visitor Parity

Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T063447Z`

Base accepted HEAD: `263ff1b299519d64e76087161433531b7a3e8cf2`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `napi/src/at_rule_parser.rs` receives the custom at-rule name from cssparser as a decoded `CowRcStr`, looks it up in `CustomAtRuleParser.configs`, and serializes `AtRule.name` with `serialize_identifier`.
- `tests/test_custom_parser.rs` uses cssparser `AtRuleParser::parse_prelude()` plus `Ident::parse(input)?`; this keeps custom parser names and preludes on the decoded identifier path before minified output is printed.

## Behavior

- `CustomAtRuleTransformer::parseAtPrelude()` now scans CSS at-keyword names with CSS identifier escape handling instead of relying on an ASCII-only regex.
- Escaped custom at-rule names such as `@m\69 xin` and nested escaped statements such as `@ap\70 ly` now resolve to the configured `mixin` / `apply` custom parser entries before visitor dispatch.
- Preserved custom parser output serializes the decoded at-rule name, e.g. `@bl\6f ck hero { color: yellow }` prints as `@block hero{color:#ff0}`.

## Red-First Evidence

Before the source patch, the new focused test failed at parser lookup:

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
FAIL custom at-rules decode upstream escaped at-rule names before parser lookup
Invalid CSS at-rule prelude: @m\69 xin card
1 test files, 307 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
1 test files, 310 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 6461 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-custom-at-rule-escaped-name-visitor.php --self-test
OK
```

Additional hygiene:

```text
php -l lanes/lightningcss/src/CustomAtRuleTransformer.php
php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php
php -l lanes/lightningcss/examples/wordpress-custom-at-rule-escaped-name-visitor.php
php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status OK\n";'
git diff --check -- lanes/lightningcss
```

All passed locally.

## Status Delta

- Focused custom at-rule assertions: `307 -> 310` (`+3`) for this micro-slice.
- Full LightningCSS lane assertions: `6458 -> 6461` (`+3`) compared with the accepted lane status in this worktree.
- Conservative mapped coverage remains `2360 / 3532`; this deepens the already represented custom at-rule parser/visitor cluster rather than adding a new upstream denominator row.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.
- Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP custom at-rule scanner, CSS escape decoder, SyntaxString prelude parser, visitor dispatch, and existing WordPress example harness. No Node, Rust, WASM, browser service, parser generator, network service, or external CSS engine is introduced.

## Non-Overlap

This does not repeat accepted escaped custom parser preludes, escaped CSS Modules identifiers, escaped import specifiers, custom at-rule token-list comma parsing, token-array replacement re-entry, raw env spacing, nested returned-body parsing, or typed prelude visitor traversal. It is scoped to the at-keyword name scanner before custom at-rule parser lookup and visitor dispatch.

## Next

Continue with remaining non-overlapping LightningCSS custom at-rule parser/visitor behavior, or pivot to source maps, CSS Modules, bundle/import graph, media queries, property values, target prefixing, or CSSOM with full-lane PHP gates.
