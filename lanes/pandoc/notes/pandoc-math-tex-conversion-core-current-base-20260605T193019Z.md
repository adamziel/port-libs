# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T193019Z`

Base accepted HEAD: `8136e31e3cbc131cb905067bff7696d833252432`

## Behavior Added

- Added bounded native Math/TeX handoff for `flalign`, `flalign*`,
  `flaligned`, and `flaligned*` environments.
- `MathTexConverter` now emits MathML `mtable` output for those environments
  with alternating `left right` column alignment based on the widest row.
- Row-level `\tag`, `\tag*`, and `\label` metadata reuse the existing AMS row
  metadata path, including document reference-label extraction.
- Ragged flush-aligned rows remain visible for review instead of being padded
  or dropped.
- Malformed rows with no alignment markers, empty final rows, or more than
  eight bounded columns are rejected before exposing MathML to WordPress
  review packets.
- Updated the WordPress math handoff example so importer review packets include
  flush-aligned MathML and escaped source-TeX annotations without invoking
  renderers.

## Source Truth

- The accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The texmath TeX reader source maps `flalign`/`flalign*` and
  `flaligned`/`flaligned*` through AMS array-style row handling with
  alternating left/right alignment:
  https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs
- Prior Math/TeX slices accepted fractions, roots, scripts, delimiters,
  source annotations, equation references, AMS align/gather/split/alignedat,
  multline/multlined, compact environments, and equation/equation* wrappers.
  This slice owns only the bounded flush-aligned row-environment handoff.
- No local Pandoc or texmath checkout is present under this isolated worktree,
  so no upstream Haskell runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 377 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 380 assertions, 1 failures`.
  - Failure reason: `Unsupported TeX environment flalign at offset 15`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 385 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+8` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint, JSON validation, and whitespace checks are recorded in the worker
  final report.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`,
`AstNode`, `WordPressBlockWriter`, WordPress Math/TeX example, and focused PHP
test harness. Full upstream Pandoc runner parity remains the existing
Cabal/upstream-checkout blocker recorded in lane status.

No Pandoc, Cabal solver/build/test command, Haskell runner, `texmath`, MathJax,
KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, online
service, or live service was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1502` -> `1503`.
- `phpPass`: `1049` -> `1050` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `62`.
- Focused `MathTexConverterTest.php`: `48` -> `49` PASS cases and `377` ->
  `385` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX fractions, generalized fractions, infix
  fractions, roots, scripts, source annotations, delimiters, sized delimiters,
  `\middle`, large operators, functions, operator names, explicit
  `\limits`/`\displaylimits`, relation/set/logic commands, accents,
  extensible arrows, macro expansion, matrix/array/cases/subarray conversion,
  `substack`, AMS align/gather/split/alignedat conversion, multline/multlined
  conversion, equation wrappers, row tags, top-level equation label/tag
  rendering, equation references, automatic numbering outside wrappers,
  color/phantom/cancel/layout boxes, math alphabet variants, named/explicit
  spacing, or accessible MathML metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep `\notag`/`\nonumber` row numbering policy, finer display numbering and
starred-environment policy, nested equation wrapper policy, cross-document
equation-reference maps, package macro expansion, renderer validation, complex
accessibility intent grammar, full `texmath` parity, and full upstream runner
dependency planning as separate bounded slices.
