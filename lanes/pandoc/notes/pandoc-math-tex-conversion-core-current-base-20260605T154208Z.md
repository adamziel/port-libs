# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T154208Z`

Base accepted HEAD: `cc5990fba07cfe24ac4db3a1208b8183f8821c17`

## Behavior Added

- Added bounded native Math/TeX handoff for compact table-style environments:
  `smallmatrix` and `subarray`.
- `\begin{smallmatrix}...\end{smallmatrix}` now emits a script-level MathML
  `mtable` with compact row and column spacing, preserving editable source TeX
  in the existing `application/x-tex` semantics annotation.
- `\begin{subarray}{c}...\end{subarray}` and sibling `l`/`r` specs now emit
  bounded MathML tables for use in scripts under operators such as `\sum` and
  `\prod`.
- Malformed `subarray` inputs with missing/empty/unsupported column specs,
  mismatched row widths, or empty compact matrices are rejected before MathML
  is exposed.
- Updated the WordPress math handoff example so review packets keep editable
  compact-environment TeX and expose the corresponding native MathML.

## Source Truth

- The accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`.
  Those fixtures preserve inline/display math source as TeX strings in Pandoc
  math nodes.
- Prior Math/TeX slices accepted ordinary matrix/array/AMS row environments,
  `substack`, alignedat, equation labels/references, and accessibility
  annotations. This slice owns only compact `smallmatrix`/`subarray` handoff.
- No local Pandoc or texmath checkout is present under this isolated worktree,
  so no upstream Haskell runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 325 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 330 assertions, 1 failures`.
  - Failure reason: `Unsupported TeX environment smallmatrix`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 335 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+10` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint, JSON validation, and whitespace checks are recorded in the worker
  final report.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

No Pandoc, Cabal solver/build/test command, Haskell runner, `texmath`,
MathJax, KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer,
or online service was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1432` -> `1433`.
- `phpPass`: `977` -> `978` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `64`.
- Focused `MathTexConverterTest.php`: `43` -> `44` PASS cases and `325` ->
  `335` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX fractions, generalized fractions, infix
  fractions, roots, scripts, source annotations, delimiters, sized delimiters,
  `\middle`, large operators, functions, operator names, relation/set/logic
  commands, accents, extensible arrows, macro expansion, ordinary matrix and
  array environments, `cases`, `substack`, AMS align/gather/split,
  `alignedat`, above/below/style wrappers, color/phantom/cancel/layout boxes,
  math alphabet variants, Unicode math alphabet rewriting, equation labels,
  equation references, named/explicit spacing, or accessible MathML metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep full `texmath` parity, TeX package macro expansion, multiline equation
environments such as `multline`, cross-document equation-reference maps,
renderer validation, complex accessibility intent grammar, and full upstream
runner dependency planning as separate bounded slices.
