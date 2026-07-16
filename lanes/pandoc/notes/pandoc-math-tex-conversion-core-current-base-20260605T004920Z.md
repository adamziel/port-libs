# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T004920Z`

Base accepted HEAD: `291caa6ad56f611338659462f05dd5385605317b`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX `array` environment handoff.
- Parses the required array column spec group after `\begin{array}`, maps `l`,
  `c`, and `r` to MathML `columnalign`, and ignores vertical rule markers such
  as `|` because the current MathML handoff owns alignment, not visual border
  drawing.
- Reuses the existing top-level row/cell splitting and expression parser, so
  fractions, scripts, Greek symbols, relation operators, and source TeX
  annotations survive without invoking texmath, Pandoc, MathJax, KaTeX, or a
  TeX engine.
- Added malformed guards for missing, empty, and unsupported array column specs.
- Updated `examples/wordpress-math-tex-handoff.php` so the WordPress review
  smoke preserves an editable array formula and emits matching bounded MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`, `test/markdown-reader-more.txt`,
  and `test/markdown-reader-more.native`: Pandoc preserves inline/display math
  source as TeX strings in math nodes.
- Prior math notes explicitly left `array` column-spec parsing as follow-up
  after matrix/aligned/cases environment handoff. This slice ports that bounded
  support-library contract only.
- This does not attempt full `texmath` parity, TeX rendering, MathJax, KaTeX,
  Pandoc execution, TeX/PDF engines, `p`/`m`/`b` paragraph columns, row spacing,
  or broader macro expansion.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 75 assertions, 0 failures`.
- Red check after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: failed the new array-environment case with
    `Unsupported TeX environment array at offset 13`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 81 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+6` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

## Non-Overlap

- Does not repeat accepted Math/TeX fraction/root/script/text, source
  annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, indexed-root,
  matrix/aligned environment, or cases environment conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, or upstream-runner dependency closure.

## Follow-Up

Keep binomial commands, richer array column specs such as `p`/`m`/`b`, optional
macro arguments, richer MathML intent annotations, deeper TeX parsing, and full
upstream runner dependency planning as separate bounded slices.
