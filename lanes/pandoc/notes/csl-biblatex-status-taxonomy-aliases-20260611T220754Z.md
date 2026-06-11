# CSL/BibLaTeX Status Taxonomy Aliases

Bead: `plib-tnlzo`

## Scope

- Added bounded native PHP BibTeX/BibLaTeX ingestion aliases for `publication-status`/`publicationstatus`, `keyword-list`/`keywordlist`, and `category-list`/`categorylist`.
- Preserves normalized CSL `status`, `keywordSummary`, and `categorySummary` metadata through citation rendering, bibliography rendering, WordPress handoff, and the legacy `BibtexCslProcessor` path.
- No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Accounting

- `phpPass`: `3133 -> 3134`
- `mappedCslBiblatexStatusTaxonomyAliasCases`: `1`
- `cslBiblatexStatusTaxonomyAliasAssertions`: `26`

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php` -> `2 test files, 5026 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 66710 assertions, 0 failures`
