# Custom At-Rule Returned Media Exit Parity - 2026-06-01

## Source Truth

- Upstream LightningCSS pinned cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Relevant behavior: `napi/src/transformer.rs` visits children for replacement at-rules before applying the replacement rule exit visitor.

## Delta

- Returned `type: media` and `type: supports` rule objects now serialize their returned child rules before building the exit visitor payload.
- Returned child style rules run selector/declaration/value traversal before the parent returned at-rule exit payload is built.
- Exit replacements are emitted with a suppression guard so a `RuleExit.media` or `RuleExit.supports` replacement does not recursively apply the same exit visitor.
- Added a WordPress-facing smoke for a custom viewport at-rule returning media around `.wp-block-cover` CSS.

## Evidence

- Red-first focused check before the source fix:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Failed `custom at-rules visit upstream returned media replacement children before exit visitors`: output stayed at `@media (width>=40rem)` because the returned media `RuleExit` visitor was not called.
- Passing focused check after the source fix:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 393 assertions, 0 failures`

## Non-Overlap

This slice does not repeat the accepted SyntaxString literal-start parser parity, escaped/unicode at-rule name parser parity, or returned raw rule-object serialization slices. It is limited to returned replacement at-rule child traversal and exit payload parity.

## Dependency Closure

No new support component is needed. The patch reuses the existing custom at-rule parser, declaration/value visitor, media/supports visitor, and PHP test runner surfaces.

## Next

Continue custom at-rule parity on bounded parser/visitor gaps that are still absent from upstream-backed tests, especially replacement traversal cases that involve nested custom or unknown rules.
