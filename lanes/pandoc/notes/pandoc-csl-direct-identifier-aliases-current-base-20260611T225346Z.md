# Pandoc CSL direct identifier alias slice

Slice: `plib-6mk3l`

Base: `e25fac12621238ccfbf5b538de7ebfd27f763613`

## Summary

CSL citation/bibliography handoff now normalizes direct CSL JSON compact ISBN and ISSN identifier aliases into canonical `isbn` and `issn` metadata. Covered aliases include `isbn13`, `isbn10`, `eISBN`, `e-isbn`, `printISSN`, `pISSN`, `eISSN`, and `onlineISSN` forms.

The slice preserves raw alias provenance on normalized items, renders the aliases through CSL text variables, and carries default bibliography plus WordPress appended bibliography output without invoking Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed: 1 test file, 4867 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66861 assertions, 0 failures

## Lane Status

- Added one focused `CitationCslProcessorTest` PASS case with 18 assertions.
- `phpPass` moved from 3135 to 3136.
- `phpFail` remains 0.
