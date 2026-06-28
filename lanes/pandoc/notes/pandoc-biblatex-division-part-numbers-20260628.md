# Pandoc BibLaTeX Division And Part Number Slice

Slice: `pandoc-biblatex-division-part-numbers-20260628`

Bounded native PHP Citation/CSL handoff now preserves legacy BibLaTeX
division/subdivision, part/part-number, printing-number, and supplement-number
metadata through `BibtexCslProcessor`.

## Scope

- `division` and `subdivision` map to CSL `division`.
- `part`, `partnumber`, and `part-number` map to CSL `part`.
- `printingnumber`, `printing-number`, `printnumber`, `print-number`, and
  `printing` map to CSL `printing-number`.
- `supplementnumber` and `supplement-number` map to CSL `supplement-number`.
- Direct bibliography text exposes each preserved value for review queues.
- `CitationCslProcessor` receives the normalized variables through `fromItems`,
  so styled citation and bibliography rendering plus WordPress bibliography
  output can review them without external citeproc.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - Result: `1 test files, 766 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `295 test files, 117003 assertions, 9781 failures`
  - The broad lane remains baseline-red; visible failures include unrelated
    `YamlMetadataReviewTest.php` expectations.

## Accounting

- `lane-status.json` `phpPass`: `463 -> 464`
- `UPSTREAM_TEST_MANIFEST.json`: added
  `mappedLegacyBiblatexDivisionPartNumberCases: 1`

No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser
renderer, external validator, online service, live provider test, or
live-service provider test was invoked.
