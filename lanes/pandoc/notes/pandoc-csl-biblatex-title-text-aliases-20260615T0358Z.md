# Pandoc CSL BibLaTeX Title Text Aliases

Bead: `plib-2gkmq`
Base: `be4be5659`

This slice keeps BibLaTeX title-text metadata self-contained in the native PHP
CSL handoff. `BibtexCslParser` now routes hyphenated and compact title-text
aliases into the canonical CSL metadata used by citation rendering,
bibliography rendering, sorting, and WordPress block output:

- `book-title`, `container-title-text`, `containertitletext`,
  `publication-title`, and `publicationtitle` -> `container-title`
- `main-title-text` and `maintitletext` -> `main-title`
- `volume-title-text` and `volumetitletext` -> `volume-title`
- `part-title-text` and `parttitletext` -> `part-title`
- `issue-title-text` and `issuetitletext` -> `issue-title`
- `collection-title-text` and `collectiontitletext` -> `collection-title`

The focused fixture covers both hyphenated and compact input forms, subtitle
composition, raw BibTeX metadata retention, normalized CSL item fields, CSL text
variables, sort order, bibliography entries, and WordPress handoff.

Verification on 2026-06-15 UTC:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 5890 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 85890 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
