# Custom At-Rule Escaped Prelude Parser/Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T054045Z`

Accepted base: `663e16b4022673e2529b925ce20b45f0a578189e`

## Source truth

- Upstream pinned LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_custom_parser.rs` parses custom at-rule preludes with `Ident::parse(input)?`, so CSS escapes are decoded before custom parser validation and serialization.
- `node/test/customAtRules.mjs` and `node/test/visitor.test.mjs` cover custom at-rule SyntaxString parsing and identifier visitors; this slice ports the escaped identifier edge for those already mapped surfaces.

## Behavior

- `<custom-ident>` custom at-rule preludes now decode CSS escapes such as `h\65 ro` before validation, serialization, and `CustomIdent` visitor callbacks.
- `<dashed-ident>+` preludes now keep hex-escape terminator whitespace inside the token, decode values such as `--wp\2d accent`, and expose decoded dashed identifiers to `DashedIdent` visitors.
- Literal SyntaxString preludes now match escaped literal identifiers such as `comp\61 ct` and expose decoded serialized preludes to custom rule visitors.

## Evidence

- Red-first focused run before source changes:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Failed the two new escaped prelude cases with `Invalid custom at-rule prelude for <custom-ident>`.
- Passing focused run after implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 293 assertions, 0 failures`
- Full lane run:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6248 assertions, 0 failures`
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-custom-at-rule-escaped-prelude-visitor.php --self-test`
  - `OK`

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP CSS escape decoder, custom at-rule SyntaxString parser, identifier visitor callbacks, and WordPress example bootstrap.

## Non-Overlap

This does not repeat accepted repeated literal SyntaxString parsing, typed component visitor coverage, dashed/custom identifier visitor dispatch, import graph escaped identifiers, CSS Modules escaped identifiers, media escaped range features, or target-prefixing slices. It is limited to cssparser-style escaped identifier decoding in custom at-rule parser preludes before visitor traversal.

## Next Task

If another custom at-rule slice is needed, consider escaped custom at-rule names or remaining compose visitor value surfaces that are not already covered by the existing custom parser, visitor, and token-list tests.
