# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T032453Z`

Base accepted HEAD: `b3f4a458caf974825db7d13e0547615ffa201d28`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX color, phantom, and cancel
  command handoff.
- Converts grouped `\color{...}{...}` and `\textcolor{...}{...}` to MathML
  `mstyle mathcolor` wrappers for safe named colors and `#rgb` / `#rrggbb`
  values only.
- Converts `\phantom`, `\hphantom`, and `\vphantom` to bounded
  `mphantom` / `mpadded` MathML output so invisible spacing survives reviewer
  math handoff.
- Converts `\cancel`, `\bcancel`, and `\xcancel` to MathML `menclose` strike
  notations.
- Rejects empty groups, missing groups, and unsafe color values before exposing
  MathML, without invoking Pandoc, texmath, MathJax, KaTeX, a TeX engine, or
  any online service.
- Updated `examples/wordpress-math-tex-handoff.php` so WordPress review
  packets preserve the editable TeX source and expose matching bounded MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Previous math notes left color/phantom/cancel policy as a separate follow-up
  after direct fractions, generalized/infix fractions, roots, scripts, fences,
  operators, matrices, cases, arrays, binomials, accents, macros, and
  above/below/style wrappers.
- This slice ports that bounded support-library contract only. It does not
  attempt full `texmath` parity, scoped color declarations, xcolor model
  conversion, `\cancelto`, `\overwithdelims`, optional macro arguments, MathML
  intent annotations, or renderer execution.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 126 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 129 assertions, 2 failures`.
  - Failure reason: `\color` / `\textcolor` were emitted as literal
    identifiers and malformed color/phantom/cancel commands were accepted.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 146 assertions, 0 failures`.
  - Delta: `+2` focused PASS cases and `+20` focused assertions.
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6371 assertions, 0 failures`.
  - PASS-line count command:
    `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `576`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

## Status Delta

- `phpPass`: remains exact focused-suite PASS-line count `576`.
- `benchmarkDenominator.mapped`: `1053` -> `1055`.
- `mathTexConversionCoreCases`: `11` -> `13`.
- `mappedMathTexConversionCoreCases`: `11` -> `13`.
- `mathTexConversionCoreAssertions`: `54` -> `74`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, roots, scripts, source annotation,
  delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, indexed-root,
  matrix/aligned environment, cases environment, array column-spec,
  above/below/style wrapper, or binomial command conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep color declaration scoping, named xcolor model conversion, `\cancelto`
target annotations, `\overwithdelims`, `\atopwithdelims`, `\abovewithdelims`,
optional macro arguments, richer MathML intent/accessibility annotations,
deeper TeX parsing, and full upstream runner dependency planning as separate
bounded slices.
