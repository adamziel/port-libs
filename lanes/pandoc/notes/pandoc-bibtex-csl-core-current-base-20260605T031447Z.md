# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T031447Z`
Base: `f94cbdd4376265f512a55fce6d52a7d1fcd0e4c1`

## Behavior

- Added bounded BibLaTeX ISO date interval support for bibliography handoff.
- `BibtexCslParser` now maps date strings such as `2020-05/2021-06`,
  `2018/2019`, and `2025-01-01/2025-01-31` into CSL `date-parts` ranges
  instead of preserving them only as literal strings.
- `CitationCslProcessor` now carries date `rangeParts` and display metadata
  through issued, original, accessed, and event dates, including default
  author-date labels, default bibliography entries, custom CSL `<date>`
  rendering, and WordPress block output.
- Updated `wordpress-bibtex-csl-handoff.php` so reviewer queues expose
  date-range source metadata without invoking Pandoc, citeproc, BibTeX, Biber,
  or bibliography managers.

## Source Truth

- This is a bounded native-PHP port of the BibLaTeX/CSL handoff contract:
  BibLaTeX date fields may represent intervals, and CSL JSON represents date
  intervals as multiple `date-parts` arrays.
- The slice reuses the existing local `BibtexCslParser`, `CitationCslProcessor`,
  `MarkdownReader`, and `WordPressBlockWriter` support. It does not shell out to
  Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX, Biber, Word,
  LibreOffice, archive tools, TeX/PDF engines, browser renderers, or online
  services.

## Verification

- Red check:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: failed with 1 failure because `2020-05/2021-06` was emitted as
    `['literal' => '2020-05/2021-06']` instead of CSL ranged `date-parts`.
- Focused green:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 442 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-handoff self-test passed`.
- Full lane focused suite:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6257 assertions, 0 failures`.
- PASS-line count:
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `568`.

## Mapping Delta

- `mappedBibtexCslCoreCases`: `2 -> 3`.
- `bibtexCslCoreAssertions`: `38 -> 59`.
- Manifest mapped count: `1046 -> 1047`.
- Citation focused assertions: `421 -> 442`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, BibTeX/BibLaTeX entry parsing, TeX accent decoding, crossref
inheritance, xdata inheritance, source-file attachment policy, entry-set/related
metadata, translation/original-publication metadata, legal/patent metadata, CSL
style XML/locales, sort keys, name options, macros, choose conditionals,
bracketed citation cluster parsing, PDF engine handoff, EPUB3, DOCX/ODT,
table-geometry, YAML, ZIP/OPC, archive-compression, charset/Unicode, or
XML/HTML5 DOM work.

## Dependency Closure

No new support component is needed. This reuses existing native PHP BibTeX/CSL,
AST, Markdown, and WordPress writer code. Full upstream Pandoc/citeproc runner
parity remains gated on hydrating the pinned Pandoc checkout and building the
Haskell test executables; that dependency gate is unchanged by this bounded
support-library slice.

## Follow-Up

- Richer uncertain/open-ended BibLaTeX date forms, seasons, circa markers,
  disambiguation, citation-position logic, note-style output, broader style
  catalogs, and full citeproc parity remain separate bounded Citation/CSL
  slices.
