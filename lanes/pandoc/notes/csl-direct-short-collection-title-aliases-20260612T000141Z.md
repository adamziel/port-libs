# CSL Direct Short Collection Title Aliases Slice

Bead: `plib-yp3ou`
Date: 2026-06-12 UTC
Base: origin/main 35768390c9

Implemented a bounded native PHP CSL citation/bibliography slice in
`CitationCslProcessor`:

- Direct CSL JSON now normalizes `shortcollection`, `short-collection`, and
  `shortCollection` into canonical `collectionTitleShort` metadata.
- CSL text variable rendering now accepts `shortcollection`,
  `short-collection`, `shortseries`, `short-series`, `series-short`, and
  `collectiontitleshort` aliases for `collectionTitleShort`.
- Canonical `collection-title` `form="short"` rendering remains backed by
  `collectionTitleShort`.
- Raw direct CSL JSON alias provenance remains available on normalized items.

Verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> 1 test file, 4941 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 67458 assertions, 0 failures

Accounting:

- `phpPass` 3146 -> 3147
- mapped denominator 3222 -> 3223
- `mappedDirectCslShortCollectionTitleAliasCases`: 1
- `directCslShortCollectionTitleAliasAssertions`: 15

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer,
external validator, online service, live provider test, or live-service
provider test was invoked.
