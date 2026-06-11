# docx-openxml-missing-content-type-overrides-20260611T223427Z

Slice: `plib-e0jak`, DOCX/OpenXML package ingestion.
Base: current `origin/main` `71ce25fbede5`.

## Change

`DocxOpenXmlReader` package provenance summaries now aggregate
`[Content_Types].xml` override declarations that point at missing package
parts. The summary reports missing override counts, parameterized missing
override counts, and review rows with part names, existence state, raw and base
content types, parameter lists, and parameter maps.

The existing per-override provenance remains unchanged for ingestion. This
adds package-level review visibility before WordPress or other native handoff
queues decide how to inspect declared-but-absent OpenXML parts.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, Cabal/Haskell runners,
browser renderers, external validators, online services, live provider tests,
or live-service provider tests are executed.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 test file, 1025 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 66707 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3133 -> 3134`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3218 -> 3219`.
- Added one focused `DocxOpenXmlReaderTest` case with 23 assertions for
  missing content-type override summary rows.
