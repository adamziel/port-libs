# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T210613Z`

Base accepted HEAD: `c5e69f53fca004ec586e1db1a9d5907356a992ee`

## Behavior Added

- Added bounded native Math/TeX handoff for TeX `\notag` and `\nonumber`
  row-number suppression.
- `MathTexConverter` now strips top-level `\notag` and `\nonumber` from
  equation and AMS environment row metadata before rendering presentation
  MathML, so those commands no longer appear as literal MathML identifiers.
- Suppressed rows do not consume automatic equation-reference numbers when
  `numberUntagged` is enabled.
- Explicit `\tag` output remains preserved even when a row also contains a
  no-number command.
- Source annotations still preserve the original TeX, including `\notag` and
  `\nonumber`, for WordPress review and accessibility tooling.
- Updated the WordPress math handoff example so review packets show editable
  no-number TeX while bounded MathML rows remain clean and unnumbered.

## Source Truth

- The accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The latest accepted Math/TeX note explicitly left `\notag` and `\nonumber`
  row-number suppression as a bounded follow-up after prime notation.
- This slice ports only the native PHP format contract for common no-number
  TeX row metadata. It does not attempt full `texmath` parser parity.
- No local Pandoc or texmath checkout is present under this isolated worktree,
  so no upstream Haskell runner was executed.

## Verification

- Baseline focused Math/TeX check before the new test:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 403 assertions, 0 failures`.
- Red-first focused Math/TeX check after adding the no-number row test:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 405 assertions, 1 failures`.
  - Failure: `\notag` and `\nonumber` leaked into MathML as literal
    identifiers.
- Focused Math/TeX check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 411 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+8` focused assertions over the
    baseline.
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

- `benchmarkDenominator.mapped`: `1527` -> `1528`.
- `phpPass`: `1075` -> `1076` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `12` -> `13`.
- `mappedMathTexConversionCoreCases`: `12` -> `13`.
- `mathTexConversionCoreAssertions`: `63` -> `71`.
- Focused `MathTexConverterTest.php`: `51` -> `52` PASS cases and `403` ->
  `411` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX fractions, generalized fractions, infix
  fractions, roots, scripts, source annotations, delimiters, sized delimiters,
  `\middle`, large operators, functions, operator names, explicit
  `\limits`/`\displaylimits`, relation/set/logic commands, negated relation
  overlays, accents, extensible arrows, macro expansion, matrix/array/cases/
  subarray conversion, `substack`, AMS align/gather/split/alignedat/flalign
  conversion, multline/multlined conversion, equation wrappers, explicit row
  tags, equation references, automatic numbering for normal rows, prime
  notation, color/phantom/cancel/layout boxes, math alphabet variants,
  named/explicit spacing, or general accessible MathML metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep broader package macro expansion, cross-document equation-reference maps,
complex numbering policies, renderer validation, broader `texmath` parity, and
full upstream runner dependency planning as separate bounded slices.
