# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T051558Z`
Base: `4a3dfccee736ad27c0f7e3c0853ebdf8eaf9cb3e`

## Behavior

- Added bounded BibLaTeX secondary editor role handoff for `editora`,
  `editorb`, and `editorc` plus `editoratype`, `editorbtype`, and
  `editorctype`.
- `BibtexCslParser` now preserves these role lists in `editorial-roles` review
  metadata and maps common roles into CSL-style name variables including
  `compiler` and `editorial-director`.
- `CitationCslProcessor` now normalizes compiler, curator, director,
  editorial-director, illustrator, interviewer, and reviewed-author name
  variables, exposes them to bounded CSL `<names>` rendering, preserves unknown
  secondary roles such as `reviewer`, and emits compact fallback bibliography
  text for WordPress review queues.
- Updated `wordpress-bibtex-csl-handoff.php` so imported `.bib` review packets
  keep compiler, editorial director, and reviewer role metadata visible without
  invoking external bibliography tools.

## Source Truth

- This continues the accepted lane-local BibTeX/BibLaTeX-to-CSL handoff model:
  parsed `.bib` fields become CSL-like item records before native citation and
  bibliography rendering.
- The immediately previous BibTeX role slice explicitly left
  `editora`/`editoratype` dispatch as follow-up. This slice implements that
  bounded role-family only. It does not attempt localized role-term rendering,
  `nameaddon`, full BibLaTeX role vocabularies, note-style output,
  disambiguation, or full citeproc parity.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 601 assertions, 0 failures`.
- Red check:
  - Same command after adding the secondary-editor-role case.
  - Result: `1 test files, 604 assertions, 1 failures`.
  - Failure: parsed BibTeX item did not expose `compiler` metadata from
    `editora` / `editoratype={compiler}`.
- Focused green:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 621 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- New focused case: `maps bounded biblatex secondary editor roles into csl metadata`.
- Focused Citation/CSL assertions: `601 -> 621`.
- Lane PHP PASS count: `645 -> 646`.
- Manifest mapped count: `1121 -> 1122`.
- Manifest current-base BibTeX/CSL sub-counter: `2` cases / `38` assertions to
  `3` cases / `58` assertions.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, initial BibTeX/BibLaTeX parsing, TeX accent decoding,
crossref inheritance, xdata inheritance, source-file attachment policy,
entry-set/related metadata, translation/original-publication metadata,
legal/patent metadata, date-range metadata, title/subtitle/title-addon
metadata, publication detail and identifier metadata, existing editorial role
fields such as `origauthor`, `commentator`, `annotator`, `introduction`,
`foreword`, or `afterword`, CSL style XML/locales, sort keys, name options,
macros, choose conditionals, locator/page label rendering, citation-position
conditionals, PDF engine handoff, EPUB3, DOCX/ODT, table geometry, YAML,
ZIP/OPC, archive compression, charset/Unicode, XML/HTML5 DOM, syntax
highlighting, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader, and WordPress block
writer. Full upstream Pandoc/citeproc runner parity remains gated on hydrating
the pinned Pandoc checkout and building the Haskell test executables; that
dependency gate is unchanged by this bounded support-library slice.

## Follow-Up

- Keep localized role terms, `nameaddon`, `maintitle` and multi-volume title
  families, richer BibLaTeX role vocabularies, full CSL disambiguation,
  note-style output, broader style catalogs, and full citeproc parity as
  separate bounded slices.
