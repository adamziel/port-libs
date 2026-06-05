# Pandoc BibTeX/CSL Core Current Base Pagination

Slice: `pandoc-bibtex-csl-core-current-base-20260605T181335Z`
Base accepted HEAD: `9ead64905fb753cca25bfab3c1ec066d02d22a57`
Date: 2026-06-05 UTC

## Scope

Implemented one bounded BibTeX/CSL support-library behavior: BibLaTeX
`pagination` and `bookpagination` page-unit metadata now survives the native
PHP handoff.

The parser maps both fields into CSL review metadata, the processor normalizes
them as `pagination` and `bookPagination`, bounded CSL styles can render
`pagination` / `book-pagination` text variables, and `<label variable="page">`
uses `pagination` to render source page ranges as columns, lines, paragraphs,
sections, or verses.

## Changes

- `src/BibtexCslParser.php`: maps BibLaTeX `pagination` and
  `bookpagination` fields.
- `src/CitationCslProcessor.php`: normalizes the fields, exposes CSL text
  variables, uses `pagination` for page-label terms, and includes both fields
  in default bibliography review metadata.
- `src/CslStyle.php`: adds bounded default CSL terms for column, line, and
  verse labels.
- `tests/CitationCslProcessorTest.php`: adds a focused native PHP case for
  parser output, normalized metadata, custom CSL label rendering, direct CSL
  item normalization, and WordPress block output.
- `examples/wordpress-bibtex-csl-handoff.php`: adds a pagination review packet
  smoke and styled CSL page-label check.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: record one additional
  mapped native BibTeX/CSL support case.

## Verification

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1336 assertions, 0 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1356 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1031 -> 1032`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1483 -> 1484`
- Focused `CitationCslProcessorTest.php`: `+1` PASS case and `+20`
  assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `BibtexCslParser`,
`CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and
`WordPressBlockWriter` paths.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online sanitizer, or online service was
executed.

The upstream-runner dependency gate remains unchanged: hydrate the pinned
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and runner suites present before any non-mutating Cabal
plan is marked ready.

## Non-Overlap

This does not repeat accepted BibTeX/CSL slices for crossref/xdata
inheritance, source-file policy, entry sets, related entries, original/
translation metadata, legal fields, date ranges, title details, publication/
eprint metadata, journal abbreviations, page-first metadata, main-title/
multivolume metadata, note/addendum/howpublished, entry subtype, editorial
roles, name annotations, shorthand labels, short creator lists, software/
dataset metadata, event metadata, event organizers, event-label localization,
ID aliases, distributed publisher/place lists, split URL dates, library
call-number metadata, `and others` et-al sentinels, sort override metadata,
or container-author metadata. It only owns bounded BibLaTeX
`pagination` / `bookpagination` page-unit handoff and CSL page-label rendering.

## Follow-Up

Keep full BibLaTeX localization strings, pagination inheritance through more
complex crossref/xdata families, richer CSL locale coverage, note-style
citation positions, citeproc disambiguation, and upstream Haskell runner parity
as separate bounded slices.
