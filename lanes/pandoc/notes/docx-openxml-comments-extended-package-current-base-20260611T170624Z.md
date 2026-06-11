# DOCX OpenXML CommentsExtended Package Slice

## Scope

- Bead: `plib-kld2o`, DOCX OpenXML package ingestion core blocker.
- Base: `origin/main` `0091a9f73`.
- Native PHP only; no Pandoc, Word, LibreOffice, office suites, `zip`/`unzip`, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Coverage

- `DocxOpenXmlReader` now loads relationship-selected `commentsExtended.xml` parts using the Word `commentsExtended` relationship type.
- The reader preserves relationship target query/fragment suffix provenance and package content-type/inventory/type summaries through the existing package provenance layer.
- `w15:commentEx` rows are summarized with `paraId`, parent/thread state, `done` state, matched comment id/text/author via `w15:paraId`, and explicit missing-comment counts.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`: 1 test file, 633 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 64260 assertions, 0 failures.

## Accounting

- Added one focused `DocxOpenXmlReaderTest` PASS case with 37 explicit assertions.
- `lane-status.json`: `phpPass` 3078 -> 3079, `phpFail` remains 0.
- `UPSTREAM_TEST_MANIFEST.json`: mapped denominator 3199 -> 3200.
