# Pandoc CSL Direct Language Alias Slice

Bead: `plib-97obm`
Base: `65bad4e34f97ad91abc77ff4ad8c890ad52effbe`
Scope: `lanes/pandoc`

Direct CSL JSON bibliography review now accepts BibLaTeX-shaped language aliases that the BibTeX path already normalized: `langid`, `hyphenation`, `langidList`, `langid-list`, `hyphenationList`, and `hyphenation-list`. `CitationCslProcessor` maps those aliases into canonical `language` and `languageList` metadata, and CSL rendering can expose the alias variables through the normalized value.

Focused coverage in `CitationCslProcessorTest.php` adds direct JSON scalar and list aliases, style-summary checks, citation and bibliography rendering, and WordPress appended bibliography handoff without invoking Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification on current main `65bad4e34f`:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` - 1 file, 4986 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 68843 assertions, 0 failures

Lane status:

- Added one focused `CitationCslProcessorTest` PASS case with 18 assertions.
- `phpPass` moved from 3168 to 3169.
- `phpFail` remains 0.
