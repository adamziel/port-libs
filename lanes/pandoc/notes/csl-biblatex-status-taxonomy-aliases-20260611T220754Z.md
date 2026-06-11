## CSL/BibLaTeX Status Taxonomy Aliases

Slice: plib-tnlzo, 2026-06-11T22:07:54Z.

Implemented bounded CSL/BibLaTeX citation/bibliography ingestion parity for
status and taxonomy aliases:

- `publication-status` and `publicationstatus` now normalize to CSL `status`.
- `keyword-list` and `keywordlist` now normalize to keyword metadata.
- `category-list` and `categorylist` now normalize to category metadata.
- Legacy `BibtexCslProcessor` handoff accepts the same aliases for compatibility.

Coverage:

- Added `maps bounded biblatex csl status taxonomy aliases into rendered metadata`
  to `CitationCslProcessorTest.php`.
- Extended the legacy original publication/release-state test with explicit
  status, keyword, and category alias assertions.

Verification:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed: 1 test file, 4872 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed: 1 test file, 154 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed after rebase onto
  current main `f7d4a7ef5`: 44 test files, 66601 assertions, 0 failures.
