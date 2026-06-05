# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T185400Z`

Base accepted HEAD: `ac32afd78423ca66d05dc814198315d888cb5712`

## Behavior Added

- Added bounded native Math/TeX handoff for `equation` and `equation*` wrapper
  environments.
- `MathTexConverter` now unwraps wrapper content into the existing MathML
  semantics output while preserving source TeX annotations.
- Top-level `\label`, `\tag`, and `\tag*` inside the wrapper reuse the existing
  equation metadata rendering path and document reference-label maps.
- Untagged display `equation` labels receive bounded automatic reference text;
  untagged `equation*` labels remain unnumbered and resolve to the label text.
- Empty wrappers, top-level `&`, and top-level `\\` row separators are rejected
  before WordPress review MathML is exposed.
- Updated the WordPress math handoff example so importer review packets include
  numbered and starred equation-wrapper MathML without invoking renderers.

## Source Truth

- The accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Prior Math/TeX slices accepted top-level equation labels/tags, document
  reference maps, AMS row metadata, alignedat, compact environments, operator
  limit placement, and multline/multlined rows. This slice owns only the
  wrapper-environment handoff around the existing equation metadata path.
- No local Pandoc or texmath checkout is present under this isolated worktree,
  so no upstream Haskell runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 366 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 366 assertions, 1 failures`.
  - Failure reason: `Unsupported TeX environment equation at offset 16`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 377 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+11` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint, JSON validation, and whitespace checks are recorded in the worker
  final report.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`,
`AstNode`, `WordPressBlockWriter`, and focused PHP test harness. Full upstream
Pandoc runner parity remains the existing Cabal/upstream-checkout blocker
recorded in lane status.

No Pandoc, Cabal solver/build/test command, Haskell runner, `texmath`, MathJax,
KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, online
service, or live service was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1495` -> `1496`.
- `phpPass`: `1043` -> `1044` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `65`.
- Focused `MathTexConverterTest.php`: `47` -> `48` PASS cases and `366` ->
  `377` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX fractions, generalized fractions, infix
  fractions, roots, scripts, source annotations, delimiters, sized delimiters,
  `\middle`, large operators, functions, operator names, explicit
  `\limits`/`\displaylimits`, relation/set/logic commands, accents,
  extensible arrows, macro expansion, matrix/array/cases/subarray conversion,
  `substack`, AMS align/gather/split/alignedat conversion, multline/multlined
  conversion, row tags, top-level equation label/tag rendering, equation
  references, automatic numbering outside wrappers, color/phantom/cancel/layout
  boxes, math alphabet variants, named/explicit spacing, or accessible MathML
  metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep `flalign`/`flalign*` row layout, nested equation wrapper policy,
cross-document equation-reference maps, package macro expansion, renderer
validation, complex accessibility intent grammar, full `texmath` parity, and
full upstream runner dependency planning as separate bounded slices.
