# docx-openxml-content-type-base-summary-20260611T224510Z

Slice: `plib-wq1gu`, DOCX/OpenXML package ingestion.
Base: current `origin/main` `9c821d42a180`.

## Change

`DocxOpenXmlReader` package provenance summaries now aggregate package part
content-type base usage and parameterized content-type inheritance across the
package inventory. The summary reports normalized content-type base counts,
parameterized package part counts, and review rows with part names, raw/base
content types, source/default/override metadata, roles, relationship-part
state, and parameter maps.

The existing content-type declaration parsing and per-part inventory remain
unchanged. This adds package-level review visibility before WordPress or other
native handoff queues inspect DOCX package parts.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, Cabal/Haskell runners,
browser renderers, external validators, online services, live provider tests,
or live-service provider tests are executed.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 test file, 1023 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 66765 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3134 -> 3135`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3218 -> 3219`.
- Added one focused `DocxOpenXmlReaderTest` case with 21 assertions for
  package part content-type base and parameterized declaration summary rows.
