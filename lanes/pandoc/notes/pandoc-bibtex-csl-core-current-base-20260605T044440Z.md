# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T044440Z`
Base: `d64fef14f1fa7cb5d92202f011bcfe6ad27d0c75`

## Behavior

- Added bounded BibTeX/BibLaTeX editorial role name-list handoff for
  `origauthor`/`originalauthor`, `commentator`, `annotator`, `introduction`,
  `foreword`, and `afterword`.
- `BibtexCslParser` now maps those fields into CSL-style name variables before
  native citation processing.
- `CitationCslProcessor` now normalizes the same variables for direct CSL item
  arrays, exposes them to bounded CSL `<names variable="...">` rendering, and
  emits compact fallback bibliography text for WordPress review queues.
- Updated the WordPress BibTeX handoff example so role-rich review
  bibliographies keep original author, commentator, annotator, introduction,
  foreword, and afterword names visible without invoking external tools.

## Source Truth

- This follows the lane's accepted native PHP BibTeX/BibLaTeX-to-CSL handoff
  model: parsed `.bib` fields are normalized into CSL-style variables before
  native citation and bibliography rendering.
- The slice is bounded to role name-list preservation and review rendering. It
  does not implement BibLaTeX `editora`/`editoratype` role dispatch, localized
  role terms, `nameaddon`, disambiguation, note-style output, or full citeproc
  parity.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 564 assertions, 0 failures`.
- Red/fix evidence:
  - First focused run with the new role case failed at `1 test files, 582
    assertions, 1 failures` because a names-only CSL citation layout correctly
    used the existing author-date fallback. The test was tightened to include a
    literal rendering element so the bounded custom CSL rendering branch is
    selected without changing existing names-only fallback behavior.
- Focused green:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 587 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- New focused case: `maps bounded biblatex editorial role name lists into csl metadata`.
- Focused Citation/CSL assertions: `564 -> 587`.
- Manifest mapped count: `1103 -> 1104`.
- Current manifest BibTeX/CSL sub-counter moves from `2` cases / `38`
  assertions to `3` cases / `61` assertions for this current-base additive
  delta.
- Lane PHP PASS count: `629 -> 630`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, initial BibTeX/BibLaTeX parsing, TeX accent decoding,
crossref inheritance, xdata inheritance, source-file attachment policy,
entry-set/related metadata, translation/original-publication metadata,
legal/patent metadata, date-range metadata, title/subtitle/title-addon
metadata, publication detail and identifier metadata, CSL style XML/locales,
sort keys, name options, macros, choose conditionals, locator/page label
rendering, citation-position conditionals, PDF engine handoff, EPUB3, DOCX/ODT,
table geometry, YAML, ZIP/OPC, archive compression, charset/Unicode, XML/HTML5
DOM, syntax highlighting, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader, and WordPress block
writer. Full upstream Pandoc/citeproc runner parity remains gated on hydrating
the pinned Pandoc checkout and building the Haskell test executables; that
dependency gate is unchanged by this bounded support-library slice.

