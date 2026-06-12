# Pandoc CSL Direct Shelfmark Alias Slice - 2026-06-12

Bead: plib-trkdb

Scope: bounded Citation/CSL bibliography ingestion. Direct CSL JSON items can now use `shelfmark`, `shelf-mark`, or `shelfMark` as package-sourced shelf identifiers, and the processor normalizes them into the existing canonical `callNumber`/`call-number` metadata path.

Why this is narrow: the existing BibLaTeX `library`/`callnumber` handoff and CSL `call-number` rendering path already worked. This slice only closes the direct CSL alias gap so archive shelfmark metadata satisfies CSL `if variable="call-number"` conditionals, citation clusters, bibliography entries, default bibliography review text, and WordPress bibliography output.

Focused fixture: `CitationCslProcessorTest.php` adds `maps bounded direct csl shelfmark aliases into call-number metadata`, covering the compact, hyphenated, and camel-case aliases without invoking Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` - 1 file, 4992 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 69082 assertions, 0 failures
