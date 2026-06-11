# Pandoc CSL direct journal abbreviation alias slice

Slice: `plib-vk6w1`

Base: `67332814bfca8f6b242591eded4f664e2c55a388`

## Summary

CSL citation/bibliography handoff now normalizes direct CSL JSON compact journal abbreviation aliases into canonical `containerTitleShort` and `journalAbbreviation` metadata. Covered aliases include `containertitleshort`, `containerTitleAbbreviation`, `shortjournal`, `shortjournaltitle`, `journalTitleShort`, and lowercase `journalabbreviation` forms.

The slice preserves raw alias provenance on normalized items, renders the aliases through CSL text variables, and carries default bibliography plus WordPress appended bibliography output without invoking Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed: 1 test file, 4883 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66920 assertions, 0 failures

## Lane Status

- Added one focused `CitationCslProcessorTest` PASS case with 16 assertions.
- `phpPass` moved from 3137 to 3138.
- `phpFail` remains 0.
