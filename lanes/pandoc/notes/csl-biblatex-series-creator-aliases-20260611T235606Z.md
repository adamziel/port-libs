# CSL/BibLaTeX Series Creator Alias Slice

Bead: `plib-rmf09`
Date: 2026-06-11 UTC
Base: origin/main e87e631746

Implemented a bounded citation/bibliography CSL parser slice:

- `BibtexCslParser` now maps `seriescreator` and `series-creator` name fields into canonical CSL `series-creator` metadata.
- Series creator name annotations such as `seriescreator+an` and `series-creator+an:family` now attach to names instead of falling through as generic field annotations.
- Legacy `BibtexCslProcessor` now accepts the same series creator aliases for shared BibTeX/CSL handoff parity.
- Added focused coverage for raw field provenance, normalized item fields, CSL style `series-creator` rendering, bibliography entries, and WordPress review blocks.

Verification:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> 1 test file, 4951 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php` -> 1 test file, 153 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 67394 assertions, 0 failures

Accounting:

- `phpPass` 3145 -> 3146
- `mappedBiblatexSeriesCreatorAliasCases`: 1
- `biblatexSeriesCreatorAliasAssertions`: 27

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer,
external validator, online service, live provider test, or live-service provider
test was invoked.
