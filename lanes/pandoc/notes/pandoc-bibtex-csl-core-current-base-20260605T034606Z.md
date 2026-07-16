# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T034606Z`
Base: `c299e5bacc888df7894d35341e2c2d2d0cc94107`

## Behavior

- Added bounded BibLaTeX title metadata handoff for `subtitle`,
  `shorttitle`, `titleaddon`, `booksubtitle`, and `booktitleaddon`.
- `BibtexCslParser` now composes `title` + `subtitle` and `booktitle` +
  `booksubtitle` into CSL `title` and `container-title` values while preserving
  `short-title`, `title-addon`, and `container-title-addon` as separate review
  metadata.
- `CitationCslProcessor` now normalizes those fields, exposes them to CSL
  `<text variable="short-title">`, `<text variable="title-addon">`, and
  `<text variable="container-title-addon">`, and includes title additions in
  fallback bibliography output.
- Updated `wordpress-bibtex-csl-handoff.php` so WordPress review queues keep
  imported bibliography subtitles and title additions visible without invoking
  Pandoc, citeproc, BibTeX, Biber, or bibliography managers.

## Source Truth

- This is a bounded native-PHP port of the lane's accepted BibLaTeX-to-CSL
  handoff contract: BibLaTeX title-family fields become CSL-compatible item
  metadata before local citation and bibliography rendering.
- The slice intentionally does not attempt title-case localization, full
  citeproc title macro behavior, note-style output, disambiguation, or broader
  style-catalog parity.

## Verification

- Red check:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: failed with 1 failure because expected
    `Migration Manual: Reviewer Packet Guide` was still emitted as
    `Migration Manual`.
- Focused green:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 487 assertions, 0 failures`.
- PASS-line count:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php | rg -c '^PASS '`
  - Result: `24`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-handoff self-test passed`.
- PHP lint:
  - `php -l lanes/pandoc/src/BibtexCslParser.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`
  - Result: no syntax errors.
- Lane directory check attempted:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6575 assertions, 1 failures`.
  - Failure is outside this slice: `MarkdownReaderTest.php` structured HTML
    table footer expectation omits `id` attributes on total-population and
    total-area cells while actual WordPress output preserves those cell ids.

## Mapping Delta

- `mappedBibtexCslCoreCases`: `3 -> 4`.
- `bibtexCslCoreAssertions`: `59 -> 78`.
- Manifest mapped count: `1066 -> 1067`.
- Citation focused assertions: `468 -> 487`.
- Lane PHP PASS count: `586 -> 587`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, initial BibTeX/BibLaTeX entry parsing, TeX accent decoding,
crossref inheritance, xdata inheritance, source-file attachment policy,
entry-set/related metadata, translation/original-publication metadata,
legal/patent metadata, date-range metadata, CSL style XML/locales, sort keys,
name options, macros, choose conditionals, locator/page label rendering, PDF
engine handoff, EPUB3, DOCX/ODT, table-geometry, YAML, ZIP/OPC,
archive-compression, charset/Unicode, or XML/HTML5 DOM work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader, and WordPress block
writer. Full upstream Pandoc/citeproc runner parity remains gated on hydrating
the pinned Pandoc checkout and building the Haskell test executables; that
dependency gate is unchanged by this bounded support-library slice.

## Follow-Up

- Keep title-case localization, localized punctuation around title additions,
  richer BibLaTeX title-family fields such as `maintitle`, number rendering,
  disambiguation, citation-position logic, note-style output, broader style
  catalogs, and full citeproc parity as separate bounded slices.
