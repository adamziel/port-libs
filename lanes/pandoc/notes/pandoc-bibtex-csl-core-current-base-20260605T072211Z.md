# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T072211Z`
Base: `98b71ec32c8dc16593cb12f8035fb086cd002024`

## Behavior

- Added bounded BibLaTeX software/dataset release-state metadata handoff for
  `version` and `pubstate`.
- `BibtexCslParser` maps BibLaTeX `pubstate` to CSL-style `status` and
  preserves `version` on parsed CSL items.
- `CitationCslProcessor` normalizes `version`, exposes bounded CSL
  `<text variable="version">` rendering, and emits compact fallback
  bibliography text for version/status metadata on non-legal bibliography
  entries.
- Updated `wordpress-bibtex-csl-handoff.php` so imported `.bib` review
  packets keep software and dataset release metadata visible without invoking
  Pandoc, citeproc, BibTeX, Biber, or bibliography managers.

## Source Truth

- This follows BibLaTeX's `version` and `pubstate` fields and the lane-local
  BibTeX/BibLaTeX-to-CSL handoff contract: source bibliography metadata is
  preserved in native PHP item structures before local citation and
  bibliography rendering.
- The slice is bounded to metadata preservation, CSL variable exposure, and
  WordPress review output. It does not attempt localized publication-state
  terms, richer software/dataset citation styles, citation disambiguation,
  note-style citations, or full citeproc parity.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 758 assertions, 0 failures`.
- Focused green:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 781 assertions, 0 failures`.
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

- New focused case:
  `maps bounded biblatex software dataset version and pubstate metadata`.
- Focused Citation/CSL assertions: `758 -> 781` (+23).
- Lane PHP PASS count: `740 -> 741`.
- Manifest mapped count: `1199 -> 1200`.
- BibTeX/CSL sub-counter: `2 cases / 38 assertions -> 3 cases / 61 assertions`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, initial BibTeX/BibLaTeX parsing, TeX accent decoding,
crossref inheritance, xdata inheritance, source-file attachment policy,
entry-set/related metadata, translation/original-publication metadata,
legal/patent metadata, date-range metadata, title/subtitle/title-addon
metadata, publication detail and identifier metadata, main-title/multi-volume
metadata, note/addendum/howpublished metadata, secondary editor role metadata,
name-addon/name-annotation metadata, CSL style XML/locales, sort keys, name
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

Keep localized publication-state terms, richer software/dataset style
behavior, citation disambiguation, note-style citation output, broader CSL
style catalogs, and full citeproc parity as separate bounded slices.
