# Pandoc Direct CSL Issue Title Text Aliases

Slice: `pandoc-csl-direct-issue-title-text-aliases-20260615T0045Z`

Implemented a bounded Citation/CSL direct JSON handoff for issue-title text aliases:

- `issueTitleText`
- `issue-title-text`
- `issuetitletext`

These aliases now normalize into canonical `issueTitle` metadata, render through `issue-title-text`, and participate in citation and bibliography sort keys. The focused coverage also keeps `issue-title-addon` visible in citation, bibliography, and WordPress review output.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 5744 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 84218 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json`

## Accounting

- `phpPass`: `3586 -> 3587`
- `phpFail`: `0`
- `mappedCslDirectIssueTitleTextAliasCases`: `1`
- `cslDirectIssueTitleTextAliasAssertions`: `22`
