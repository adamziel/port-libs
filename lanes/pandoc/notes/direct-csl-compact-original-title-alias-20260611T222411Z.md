# Direct CSL Compact Original Title Alias Handoff

This slice maps one bounded native PHP CSL/csljson citation-bibliography blocker:
direct CSL JSON compact original-title aliases now compose `origtitle` plus
`origsubtitle` and `originaltitle` plus `originalsubtitle` into canonical
`originalTitle`. Compact addendum aliases `origtitle-addon` and
`original-titleaddon` are accepted as `originalTitleAddon`.

The behavior is intentionally native and bounded. It reuses
`CitationCslProcessor`, existing CSL text-variable rendering, bibliography
rendering, Markdown citation parsing, and WordPress block output. It does not
invoke Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

Accounting:
- `mappedDirectCslCompactOriginalTitleAliasCases`: 1
- `directCslCompactOriginalTitleAliasAssertions`: 20
- phpPass moves 3133 -> 3134 with phpFail held at 0.

Verification after rebasing on `origin/main` 71ce25fbe:
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed: 1 test file, 4869 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 66704 assertions, 0 failures.
