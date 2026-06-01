# Custom At-Rule Literal SyntaxString Start Parser Parity

## Source Truth

- Upstream pinned commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source file: `/home/claude/port-libs/.upstream-cache/lightningcss/src/values/syntax.rs`.
- Behavior: `SyntaxComponentKind::parse_string` treats bare literal syntax components as identifiers only when the first codepoint satisfies `is_ident_start`, which permits ASCII letters, `_`, and non-ASCII codepoints, but not `-`.

## Change

- Tightened `CustomAtRuleTransformer::tryCustomSyntaxComponentAst()` so literal `SyntaxString` grammar components no longer accept a leading `-`.
- Added focused coverage proving `-compact`, `-compact+`, and `-compact#` custom at-rule prelude grammars reject before the custom rule visitor runs, while a valid `compact` literal still transforms.
- Added a WordPress block-style example smoke for the same guard.

## Verification

- Red-first focused run before the implementation fix:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 376 assertions, 1 failures`
  - Failure: `Expected exception InvalidArgumentException was not thrown`.
- Focused run after the fix:
  - `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - Result: `1 test files, 380 assertions, 0 failures`.
- Full lane run:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 7543 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP custom at-rule parser and visitor pipeline.

## Non-Overlap

This does not change accepted `<dashed-ident>` visitor/value behavior or custom property dashed identifier handling. Conservative mapped coverage remains `2374 / 3532`; this is a parser parity fix inside the already mapped custom at-rule `SyntaxString` area.
