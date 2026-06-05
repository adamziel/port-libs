# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T054817Z`
Base: `526ad869d7da7675b3a423e96ae8ddab1ee95e78`

## Behavior

- Added bounded BibLaTeX multi-volume title metadata handoff for `maintitle`, `mainsubtitle`, `maintitleaddon`, `volumes`, `part`, `chapter`, and `pagetotal`.
- `BibtexCslParser` maps those fields to CSL-like `main-title`, `main-title-addon`, `number-of-volumes`, `part`, `chapter-number`, and `number-of-pages`.
- `CitationCslProcessor` normalizes and renders those variables for default bibliography output and bounded CSL style rendering.
- Updated the WordPress BibTeX handoff smoke for import review queue coverage.

## Source Truth

- This follows BibLaTeX's multi-volume field family and the lane-local BibTeX/BibLaTeX to CSL handoff model.
- No external bibliography processor, Pandoc runner, BibTeX, Biber, citeproc, or online service was used.

## Verification

- Baseline `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`: `1 test files, 637 assertions, 0 failures`.
- Red check `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`: `1 test files, 643 assertions, 1 failures`; parsed BibTeX items lacked `main-title`.
- Focused green `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`: `1 test files, 666 assertions, 0 failures`.
- Example smoke `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`: passed.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- New focused case: `maps bounded biblatex main title and multi volume metadata`.
- Focused assertions: `637 -> 666` (+29).
- Lane PHP PASS count: `664 -> 665`.
- Manifest mapped count: `1144 -> 1145`.
- BibTeX/CSL sub-counter carries forward the previous accepted note at `3 cases / 58 assertions`, now `4 cases / 87 assertions`.

## Non-Overlap

- This does not repeat prior CSL JSON, date/name, style layout, macro/choose, locator/page label, BibTeX crossref/xdata/source-set/entry-set/related/translation/legal/patent/date-range, title/subtitle/title-addon, publication-detail, or editorial-role slices.
- This slice is limited to bounded BibLaTeX main-title and multi-volume metadata handoff.

## Dependency Closure

- No new support component is needed.
- The slice reuses the existing native PHP BibTeX parser, CSL processor, Markdown parser/writer, and WordPress handoff example.
- Full upstream runner parity remains gated on hydrating the pinned Pandoc checkout and recording a non-mutating Cabal solver/build plan.

## Follow-Up

- Localized role terms, `nameaddon`, richer BibLaTeX role vocabularies, TeX accent decoding, full CSL locale/style behavior, citation-position disambiguation, note-style output, and full citeproc parity remain separate bounded slices.
