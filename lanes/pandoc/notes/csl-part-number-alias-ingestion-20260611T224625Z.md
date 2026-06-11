# Pandoc CSL Part Number Alias Ingestion

Bead: `plib-rbauy`

Implemented a bounded CSL citation/bibliography ingestion slice for `part-number` field aliases. `CitationCslProcessor` now normalizes direct CSL JSON `part-number`, `partNumber`, and `partnumber` values into canonical `part` metadata, and `BibtexCslParser` maps BibTeX/BibLaTeX `part-number` and compact `partnumber` fields the same way.

Verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` - 1 file, 4,907 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 67,011 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
