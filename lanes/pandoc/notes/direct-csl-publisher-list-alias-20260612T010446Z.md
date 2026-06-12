# Direct CSL Publisher List Alias Slice

## Scope

This slice covers direct CSL JSON `publisher-list` and `publisherList` metadata in the native Citation/CSL handoff.

## Behavior

`CitationCslProcessor` now uses a bounded publisher-list alias as the scalar `publisher` value when an item does not provide an explicit scalar publisher. Explicit `publisher` still wins, and the normalized `publisherList` review metadata remains available for CSL styles that render `publisher-list`.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` - 1 file, 4963 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 68322 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
