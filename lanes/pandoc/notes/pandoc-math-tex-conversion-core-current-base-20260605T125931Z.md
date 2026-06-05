# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T125931Z`

Base accepted HEAD: `7432b93e43b53e78103e7d38c8e49c883684735d`

## Behavior Added

- Added bounded TeX layout-box handoff for `\smash`, `\smash[t]`,
  `\smash[b]`, `\mathllap`, `\mathrlap`, `\mathclap`, `\llap`, `\rlap`, and
  `\clap`.
- `\smash` now emits visible `mpadded` MathML with zeroed height/depth,
  top-only height suppression, or bottom-only depth suppression.
- The lap/clap commands now emit zero-width `mpadded` MathML, with left and
  centered overlap represented through bounded `lspace` hints.
- Malformed empty layout-box contents and unsupported `\smash[...]` positions
  are rejected before exposing review MathML.
- Updated the WordPress math handoff example so importer review packets keep
  the editable TeX source and expose the matching native MathML layout-box
  handoff.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- This slice ports one bounded support-library behavior for common TeX
  layout-box commands needed by richer MathML handoff. It does not attempt full
  `texmath` parity or TeX layout rendering.
- No local Pandoc or texmath checkout is present under
  `/home/claude/port-libs/.upstream-cache`, so no upstream Haskell runner was
  executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 291 assertions, 0 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 308 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+17` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- Final PHP lint, JSON validation, and whitespace checks are recorded in the
  worker final report.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity
remains the existing Cabal/upstream-checkout blocker recorded in lane status.

No Pandoc, texmath, Cabal solver/build/test command, Haskell runner, MathJax,
KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, or
online service was executed.

## Status Delta

- `phpPass`: `908` -> `909` by one newly passing focused math test case.
- `benchmarkDenominator.mapped`: `1366` -> `1367`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `71`.

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
  equation metadata, document equation-reference label maps, or unresolved
  reference link fallback behavior.
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
