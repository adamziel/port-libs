# Direct CSL container title aliases

Bead: `plib-q85xh`
Base verified: `e9d25106ae`

## Scope

`CitationCslProcessor` now normalizes direct CSL JSON container title aliases commonly emitted by bibliographic APIs into canonical CSL metadata:

- `publicationTitle`, `publication-title`, `publicationtitle`
- `journalTitle`, `journal-title`, `journaltitle`
- `bookTitle`, `book-title`, `booktitle`
- `proceedingsTitle`, `proceedings-title`, `proceedingstitle`

Matching short or abbreviation fields now feed `containerTitleShort` and `journalAbbreviation` for publication, journal, book, and proceedings title aliases. Bounded CSL style rendering can also address those alias variable names and their short forms while preserving canonical `container-title` output.

## Coverage

Added `normalizes bounded direct csl json container title aliases` to `CitationCslProcessorTest`.

The case verifies direct item normalization, short-form rendering, bibliography entries, and WordPress block handoff for publication, journal, book, and proceedings title aliases.

This does not repeat compact title-family aliases, source-title aliases, journal abbreviation aliases, page extent aliases, identifier aliases, or BibLaTeX parser field alias coverage.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> 1 file, 4948 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 files, 67311 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
