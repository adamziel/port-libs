# Custom At-Rule Length-Percentage Prelude Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T024352Z`

Base accepted HEAD: `d66a5b3de6df2dc65a32a2f70e37d0a3eee8d74f`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `node/index.d.ts` exposes custom at-rule `SyntaxString` component types including `<length-percentage>`.
- `napi/src/at_rule_parser.rs` parses configured custom at-rule preludes through `SyntaxString::parse_string(...).parse_value(input)` and the `AtRule` visitor traverses `self.prelude.visit(visitor)?` before serializing the custom rule.

## Behavior

Before this slice, `<length-percentage>` custom preludes parsed and serialized, but nested length dimensions skipped the `Length` visitor. A pre-change one-off check with `@space 16px` and `prelude: "<length-percentage>"` left the custom rule prelude as `16px` and recorded no `Length` visitor calls.

This slice applies the existing `Length` visitor normalization path to the `dimension` arm inside `length-percentage` parsed components. Percentages and `calc(...)` remain parsed/serialized without length traversal.

## Evidence

- Pre-change baseline: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 223 assertions, 0 failures`.
- Post-change focused gate: `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 232 assertions, 0 failures`.
- PHP lint: `php -l` passed for `CustomAtRuleTransformer.php`, `CustomAtRuleTransformerTest.php`, and `wordpress-custom-at-rule-length-percentage-prelude.php`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-custom-at-rule-length-percentage-prelude.php --self-test` -> `OK`.
- Whitespace gate: `git diff --check -- lanes/lightningcss` -> passed.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP custom at-rule parser, `Length` visitor plumbing, value normalization, and serialization paths.

## Non-Overlap

This does not repeat the accepted custom at-rule token-list, ratio, identifier, unit, image, function, env/var, selector, stylesheet, or RuleExit visitor slices. It is limited to nested length dimensions inside the already parsed `<length-percentage>` custom prelude component.
