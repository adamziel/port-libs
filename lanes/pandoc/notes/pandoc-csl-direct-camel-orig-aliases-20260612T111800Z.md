# Pandoc CSL direct camel orig aliases

Slice: `pandoc-csl-direct-camel-orig-aliases-20260612T111800Z`

## Scope

Direct CSL JSON citation and bibliography handoff now accepts camel BibLaTeX-shaped original-publication aliases:

- `origTitle`
- `origTitleAddon`
- `origDate`
- `origDateAddon`
- `origPublisher`
- `origLocation`
- `origAddress`
- `origLanguage`
- `origGenre`
- `origType`
- `origPublisherList`
- `origLocationList`
- `origAddressList`
- `origLanguageList`

The values normalize into the existing CSL original-publication fields used by citation rendering, bibliography entries, fallback review text, and WordPress bibliography review blocks.

## Verification

- Red-first focused run failed before implementation on `origTitle` normalization.
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 5199 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: `44 test files, 70787 assertions, 0 failures`

## Accounting

- Adds one focused `CitationCslProcessorTest` pass case with 26 assertions.
- Moves `phpPass` from 3198 to 3199 after rebase.
- `phpFail` remains 0.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat the accepted lower-case compact direct original-publication alias slice, BibTeX/BibLaTeX original-publication parser slice, source-title aliases, publisher authority aliases, URLDate aliases, shelfmark aliases, howpublished aliases, or container-title aliases. It is limited to direct CSL JSON camel `orig*` ingestion parity for the existing original-publication rendering path.
