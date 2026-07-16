# Pandoc CSL Direct Language List Alias Slice - 2026-06-12

Scope: bounded Citation/CSL bibliography ingestion. Direct CSL JSON items can now use compact `languagelist` metadata as an alias for canonical `languageList`, and CSL styles can render the camel `languageList` text variable through the existing `language-list` review path.

Why this is narrow: the accepted BibLaTeX primary language-list slice already handled parsed `language-list` data and direct hyphenated `language-list` input. This slice only closes the compact direct CSL/csljson alias and camel text-variable rendering gap so language lists survive citation clusters, bibliography entries, and WordPress review queues.

Verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> 1 test file, 5263 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 72327 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were run.

Accounting:

- `phpPass`: 3244 -> 3245 over current origin/main `05291e249`
- Current main already includes the direct langid/hyphenation alias counters, which are preserved alongside this slice:
  `mappedCslDirectLangidAliasCases = 1` and `cslDirectLangidAliasAssertions = 17`.
- `mappedCslDirectLanguageListAliasCases`: 1
- `cslDirectLanguageListAliasAssertions`: 13
