# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T042834Z`

Base accepted HEAD: `9fecdcbe71562bc1bac82854e69d6378cb0f5882`

## Behavior Added

- Extended `MathTexConverter` array handling with bounded `\cline{m-n}`
  support.
- Leading `\cline` commands are stripped from array row cells, validated
  against the declared array column count, and exposed as `data-tex-clines`
  or `data-tex-topclines` metadata on the MathML `mtable`.
- Malformed, reversed, zero-based, and out-of-bounds `\cline` ranges are
  rejected before MathML handoff instead of leaking literal TeX commands.
- Updated the WordPress Math/TeX handoff example so review packets preserve
  editable source TeX plus native partial array-rule metadata.

## Source Truth

- The accepted Pandoc inventory maps math preservation from `test/testsuite.txt`
  and `test/testsuite.native`: inline and display math source remains
  recoverable as TeX in Pandoc math nodes.
- The previous Math/TeX array-rule slice mapped bounded array column lines and
  `\hline` metadata and left `\cline` as a separate bounded follow-up.
- This slice ports only the bounded partial-rule handoff contract and does not
  attempt full `texmath` table/layout parity.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 442 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 435 assertions, 2 failures`.
  - Failure reason: `\cline` leaked as literal MathML and malformed ranges were
    accepted.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 450 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+8` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `MathTexConverter`
array parsing plus the existing `MarkdownReader`, `LatexWriter`, `AstNode`, and
`WordPressBlockWriter` handoff paths. Full upstream runner parity remains the
existing Cabal/Pandoc-checkout blocker recorded in lane status.

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax,
KaTeX, TeX engine, browser renderer, online sanitizer, online service, or live
provider test was executed.

## Status Delta

- `phpPass`: `1194` -> `1195`.
- `benchmarkDenominator.mapped`: `1641` -> `1642`.
- `mathTexConversionCoreCases`: `12` -> `13`.
- `mappedMathTexConversionCoreCases`: `12` -> `13`.
- `mathTexConversionCoreAssertions`: `63` -> `71`.
- Focused `MathTexConverterTest.php`: `56` -> `57` PASS cases and `442` ->
  `450` assertions.

## Non-Overlap

This does not repeat accepted Math/TeX roots, fractions, generalized fractions,
infix fractions, scripts, semantics annotations, delimiter commands, sized
delimiter mechanics, `\middle`, large operators, functions, limits,
relation/set/logic commands, negated relation overlays, accents, extensible
arrows, macro expansion, matrix/cases/subarray conversion, AMS row
environments, equation wrappers, row tags, equation references, automatic
numbering, prime notation, text-mode aliases, color/phantom/cancel/layout
boxes, math alphabet variants, spacing, alignedat, flalign, multline,
ceiling/floor/norm delimiter aliases, array column lines, or `\hline`
metadata.

This does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff,
OPC, archive compression, citations, YAML, doctemplates, tables, legacy
DOC/CFB, XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
upstream-runner dependency closure.

## Follow-Up

Keep richer array rule styling such as dashed rules, package macro expansion,
MathML intent refinements, renderer validation, full `texmath` parity, and full
upstream Pandoc runner dependency planning as separate bounded slices.
