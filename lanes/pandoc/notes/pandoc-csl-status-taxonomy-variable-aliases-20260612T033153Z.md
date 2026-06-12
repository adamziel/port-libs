# Pandoc CSL Status Taxonomy Variable Alias Slice

Bead: `plib-dalfn`
Base: `d53b14e45e8c28f2ab8aaff1a23223297ab68149`
Scope: `lanes/pandoc`

`CitationCslProcessor` now renders CSL style variables for direct status and taxonomy aliases: `publication-status`, `publicationstatus`, `pubstate`, `keyword-list`, `keywordlist`, `keyword-list-summary`, `category-list`, `categorylist`, and `category-list-summary`. The new focused case keeps those aliases visible through CSL conditionals, citation clusters, bibliography entries, and WordPress review blocks.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

Verification on current main `d53b14e45e`:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` - 1 file, 5113 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 69889 assertions, 0 failures

Lane status:

- Added one focused `CitationCslProcessorTest` PASS case with 12 assertions.
- `phpPass` moved from 3182 to 3183.
- `phpFail` remains 0.
