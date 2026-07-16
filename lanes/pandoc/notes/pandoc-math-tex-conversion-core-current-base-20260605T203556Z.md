# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T203556Z`

Base accepted HEAD: `9efea0cf0b70f996287be0cff4e9cc1b25449f37`

## Behavior Added

- Added bounded native Math/TeX handoff for TeX prime notation.
- `MathTexConverter` now renders apostrophe shorthand after math atoms as
  MathML superscript prime operators, including single, double, and triple
  prime forms.
- Prime shorthand composes with existing subscript/superscript handling, so
  review formulas such as `g''_i`, `h_i'''`, and `x^{2}'` keep the prime mark
  in the MathML script handoff.
- `\prime` and `\backprime` now parse as bounded MathML operator commands,
  including inside explicit script arguments.
- Accessibility metadata now names prime, double prime, triple prime, and back
  prime tokens for `texToAccessibleMathMl()`.
- Updated the WordPress math handoff example so derivative-style prime TeX
  stays editable in source spans and review MathML without invoking renderers.

## Source Truth

- The accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The latest accepted Math/TeX note explicitly left TeX prime handling as a
  bounded follow-up after negated relation overlays.
- This slice ports only the native PHP format contract for common prime
  notation. It does not attempt full `texmath` parser parity.
- No local Pandoc or texmath checkout is present under this isolated worktree,
  so no upstream Haskell runner was executed.

## Verification

- Focused Math/TeX check:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 403 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+9` focused assertions over the latest
    accepted math note baseline (`394` assertions).
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint:
  - `php -l lanes/pandoc/src/MathTexConverter.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/MathTexConverterTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`: no syntax
    errors.
- JSON validation:
  `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: ok.
- Whitespace check:
  `git diff --check -- lanes/pandoc`: clean.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`,
`AstNode`, `WordPressBlockWriter`, WordPress Math/TeX example, and focused PHP
test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, `texmath` runner,
MathJax, KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer,
online service, or live service was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1522` -> `1523`.
- `phpPass`: `1070` -> `1071` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `63`.
- Focused `MathTexConverterTest.php`: `50` -> `51` PASS cases and `394` ->
  `403` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX fractions, generalized fractions, infix
  fractions, roots, scripts, source annotations, delimiters, sized delimiters,
  `\middle`, large operators, functions, operator names, explicit
  `\limits`/`\displaylimits`, relation/set/logic commands, negated relation
  overlays, accents, extensible arrows, macro expansion, matrix/array/cases/
  subarray conversion, `substack`, AMS align/gather/split/alignedat/flalign
  conversion, multline/multlined conversion, equation wrappers, row tags,
  equation references, automatic numbering, color/phantom/cancel/layout boxes,
  math alphabet variants, named/explicit spacing, or general accessible MathML
  metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep `\notag`/`\nonumber` row-number suppression, broader negated relation
inventory, package macro expansion, cross-document equation-reference maps,
renderer validation, complex accessibility intent grammar, full `texmath`
parity, and full upstream runner dependency planning as separate bounded
slices.
