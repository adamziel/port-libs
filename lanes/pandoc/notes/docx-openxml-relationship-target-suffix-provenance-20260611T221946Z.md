# docx-openxml-relationship-target-suffix-provenance-20260611T221946Z

Slice: `plib-vao3k`, DOCX/OpenXML package ingestion.
Base: current `origin/main` `4172e4cba`.

## Change

`DocxOpenXmlReader` package provenance summaries now aggregate relationship
targets that carry query or fragment suffixes across root, document, and nested
relationship sidecars. The summary reports suffix-bearing relationship counts,
query counts, fragment counts, sidecars with suffix-bearing targets, and the
review rows with external, existence, target part, content-type, query,
fragment, and suffix metadata.

The existing per-relationship provenance remains unchanged for ingestion. This
adds package-level review visibility before WordPress or other native handoff
queues decide how to inspect linked package parts.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, Cabal/Haskell runners,
browser renderers, external validators, online services, live provider tests,
or live-service provider tests are executed.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 test file, 1002 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 66632 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3132 -> 3133`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3216 -> 3217`.
- Added one focused `DocxOpenXmlReaderTest` case with 24 assertions for
  relationship target suffix provenance summaries.
