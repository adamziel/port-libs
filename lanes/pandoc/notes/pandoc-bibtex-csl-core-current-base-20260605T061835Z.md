# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T061835Z`
Base: `93202104129e8ca217f3ed66d4e96d0e7e9da286`

## Behavior

- Added bounded BibTeX/BibLaTeX review metadata handoff for `note`,
  `addendum`, and `howpublished`.
- `BibtexCslParser` maps `howpublished` to CSL-style `medium` and preserves
  `note` plus `addendum` before native citation processing.
- `CitationCslProcessor` normalizes those fields for parsed BibTeX and direct
  CSL item arrays, exposes them to bounded CSL `<text variable="medium">`,
  `<text variable="note">`, and `<text variable="addendum">` rendering, and
  emits compact fallback bibliography text for WordPress review queues.
- Updated `wordpress-bibtex-csl-handoff.php` so imported `.bib` review packets
  keep source audit notes and publication medium visible without invoking
  Pandoc, citeproc, BibTeX, Biber, or bibliography managers.

## Source Truth

- This follows the lane-local BibTeX/BibLaTeX-to-CSL handoff contract: common
  `.bib` fields become CSL-compatible item metadata before local citation and
  bibliography rendering.
- The slice is bounded to note/addendum/howpublished preservation and review
  rendering. It does not attempt full note-style citations, disambiguation,
  localized role terms, name annotations, or full citeproc parity.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 683 assertions, 0 failures`.
- Red check:
  - Same command after adding the new focused case.
  - Result: `1 test files, 686 assertions, 1 failures`.
  - Failure: parsed BibTeX items did not expose `medium` from
    `howpublished`.
- Focused green:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 700 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-handoff self-test passed`.
- Syntax:
  - `php -l lanes/pandoc/src/BibtexCslParser.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`
  - Result: no syntax errors detected.
- JSON validation:
  - `lanes/pandoc/lane-status.json valid`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json valid`
- Whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: passed.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- New focused case: `maps bounded biblatex note addendum and howpublished review metadata`.
- Focused Citation/CSL assertions: `683 -> 700` (+17).
- Lane PHP PASS count: `684 -> 685`.
- Manifest mapped count: `1162 -> 1163`.
- BibTeX/CSL sub-counter: `2 cases / 38 assertions -> 3 cases / 55 assertions`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, initial BibTeX/BibLaTeX parsing, TeX accent decoding,
crossref inheritance, xdata inheritance, source-file attachment policy,
entry-set/related metadata, translation/original-publication metadata,
legal/patent metadata, date-range metadata, title/subtitle/title-addon
metadata, publication detail and identifier metadata, main-title/multi-volume
metadata, editorial-role metadata, CSL style XML/locales, sort keys, name
options, macros, choose conditionals, locator/page label rendering,
citation-position conditionals, PDF engine handoff, EPUB3, DOCX/ODT, table
geometry, YAML, ZIP/OPC, archive compression, charset/Unicode, XML/HTML5 DOM,
syntax highlighting, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader, and WordPress block
writer. Full upstream Pandoc/citeproc runner parity remains gated on hydrating
the pinned Pandoc checkout and building the Haskell test executables; that
dependency gate is unchanged by this bounded support-library slice.

## Follow-Up

Keep localized role terms, nameaddon/name-annotation parsing, citation
disambiguation, note-style citation output, broader CSL style catalogs, and
full citeproc parity as separate bounded slices.
