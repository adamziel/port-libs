# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T122713Z`

Base accepted HEAD: `83d14850b25025929d0658c79f2dae5d9193bbe0`

## Behavior Added

- Added bounded document label-map support for Math/TeX equation references.
- `MathTexConverter::equationReferenceLabelsFromDocument()` now walks parsed
  AST math nodes and collects top-level `\label`/`\tag` metadata plus AMS
  align/alignat row-level `\label`/`\tag` metadata.
- `texToMathMl()` and `mathMlFor()` accept an optional reference-label map so
  known `\ref{...}` and `\eqref{...}` handoffs display explicit tag text such
  as `WP-2` or `tag*` reviewer text while preserving the accepted unresolved
  label-text fallback.
- Duplicate normalized equation labels are rejected before exposing a review
  map, keeping WordPress formula reference packets deterministic.
- Updated the WordPress math handoff example so review packets expose collected
  equation labels and resolved known-tag references without invoking external
  renderers.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Prior math slices accepted equation references as linked unresolved-label
  handoffs and left reference-number resolution across parsed document label
  maps as follow-up. This slice owns only explicit tag lookup through a bounded
  native PHP document label map.
- No local Pandoc checkout is present under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream Haskell
  runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 286 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 286 assertions, 1 failures`.
  - Failure reason: `MathTexConverter::equationReferenceLabelsFromDocument()`
    did not exist.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 291 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+5` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- Required final lint, JSON validation, and whitespace check are recorded in
  the worker final report.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `AstNode`, `MarkdownReader`,
`LatexWriter`, and `WordPressBlockWriter` handoff paths. Full upstream Pandoc
runner parity remains the existing Cabal/upstream-checkout blocker recorded in
lane status.

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax,
KaTeX, TeX/PDF engine, browser renderer, online sanitizer, or online service
was executed.

## Status Delta

- `phpPass`: `895` -> `896` by one newly passing focused math test case.
- `benchmarkDenominator.mapped`: `1352` -> `1353`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `59`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter,
  `\middle`, large-operator/function/operator-name, relation/set/logic/arrow,
  ordinary/extensible arrow accents, simple/optional macro expansion,
  indexed-root, matrix/aligned/cases/array/alignedat conversion,
  above/below/style wrappers, binomial commands, color, phantom, cancel,
  math alphabet conversion, `\substack`, named spacing, explicit
  `\hspace`/`\mspace`, top-level equation label/tag metadata, AMS row-level
  equation metadata, or unresolved-reference link fallback behavior.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep automatic equation numbering without explicit tags, cross-document
equation references, Unicode mathematical alphanumeric rewriting, MathML
intent/accessibility annotations, nested macro-body admission, deeper TeX
parsing, full `texmath` parity, and full upstream runner dependency planning
as separate bounded slices.
