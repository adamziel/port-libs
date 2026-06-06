# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T035616Z`

Base accepted HEAD: `467a51a62878d82460d36533ed61e04c504f4164`

## Behavior Added

- Extended `MathTexConverter` array handling so TeX array column specs preserve
  interior `|` separators as MathML `columnlines` metadata.
- Added bounded `\hline` handling for arrays:
  - leading `\hline` before the first row becomes `data-tex-topline="solid"`;
  - leading `\hline` before later rows becomes MathML `rowlines="solid"`;
  - trailing final `\hline` becomes `data-tex-bottomline="solid"`;
  - `\hline` no longer leaks as a literal `<mi>\hline</mi>` review token.
- Updated the WordPress Math/TeX handoff smoke so imported review packets keep
  array-rule provenance in native MathML while retaining escaped source TeX.

## Source Truth

- The accepted Pandoc inventory maps LaTeX/math preservation from
  `test/testsuite.txt` and `test/testsuite.native`: inline/display math source
  remains recoverable as TeX in Pandoc math nodes.
- Prior Math/TeX slices already covered fractions, roots, scripts, delimiters,
  sized fences, AMS rows, `alignedat`, `flalign`, `multline`, equation
  metadata, text aliases, accents, variants, spacing, and layout controls.
- This slice only ports bounded TeX array-rule handoff metadata and does not
  attempt full `texmath` table/layout parity.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 437 assertions, 0 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 442 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+5` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`MathTexConverter`, `MarkdownReader`, `LatexWriter`, `AstNode`, and
`WordPressBlockWriter` paths. Full upstream runner parity remains the existing
Cabal/Pandoc-checkout blocker recorded in lane status.

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax,
KaTeX, TeX/PDF engine, browser renderer, online sanitizer, online service, or
live provider test was executed.

## Status Delta

- `phpPass`: `1184` -> `1185`.
- `benchmarkDenominator.mapped`: `1632` -> `1633`.
- `mathTexConversionCoreCases`: `12` -> `13`.
- `mappedMathTexConversionCoreCases`: `12` -> `13`.
- `mathTexConversionCoreAssertions`: `63` -> `68`.
- Focused `MathTexConverterTest.php`: `55` -> `56` PASS cases and `437` ->
  `442` assertions.

## Non-Overlap

This does not repeat accepted Math/TeX roots, fractions, generalized fractions,
infix fractions, scripts, semantics annotations, basic delimiter commands,
sized delimiter mechanics, `\middle`, large operators, functions, limits,
relation/set/logic commands, negated relation overlays, accents, extensible
arrows, macro expansion, matrix/cases/subarray conversion, AMS row
environments, equation wrappers, row tags, equation references, automatic
numbering, prime notation, text-mode aliases, color/phantom/cancel/layout
boxes, math alphabet variants, spacing, alignedat, flalign, multline, or the
latest delimiter-alias batch.

This does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff,
OPC, archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
upstream-runner dependency closure.

## Follow-Up

Keep broader `texmath` parity such as `\cline`, dashed rules, richer array
rule styling, package macro expansion, renderer validation, MathML intent
refinements, and full upstream Pandoc runner dependency planning as separate
bounded slices.
