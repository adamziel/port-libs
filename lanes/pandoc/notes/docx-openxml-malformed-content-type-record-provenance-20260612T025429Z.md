# DOCX OpenXML Malformed Content Type Record Provenance

This slice adds a bounded native PHP DOCX/OpenXML package-ingestion review
surface for malformed `[Content_Types].xml` declaration rows.

Base verified: `origin/main` `721755e77f`.

`DocxOpenXmlReader` now rolls OPC content-type preflight diagnostics into the
package summary:

- `contentTypeRecordCount`, `contentTypeInvalidRecordCount`, and
  `contentTypeRecordIssueCount`.
- Sorted `contentTypeRecordIssueCodes`.
- Duplicate default/override declaration counters.
- Ordered `invalidContentTypeRecords` snapshots with record index, kind,
  extension or part-name normalization fields, content type, equivalence key,
  and issue list.

The operational content-type map remains compatible with existing DOCX reads:
malformed declaration rows are reported for package review without aborting
document ingestion.

Focused verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 file, 2520 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 46 files, 75766 assertions, 0 failures.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests are invoked by this slice.
