# DOCX OpenXML XML Element Structure Provenance - 2026-06-28

## Scope

`DocxOpenXmlReader` now carries metadata-only XML element structure provenance for every XML-inspectable DOCX package part.

The package inventory and `packageProvenance.summary` expose:

- per-part XML element counts;
- leaf element counts;
- maximum element depth;
- prefixed element counts;
- namespace, local-name, and qualified-name buckets;
- compact per-part structure rows under `partXmlElementStructures`.

The slice does not expose XML text or package bytes and does not invoke Pandoc, office suites, unzip, browsers, TeX, or external validators.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file
  - 10,720 assertions
  - 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 295 test files
  - 117,693 assertions
  - 9,781 failures
  - failures are outside the touched DOCX OpenXML reader file, with visible baseline-red failures in `TableGeometryTest.php`, `UnicodeTextTest.php`, and `YamlMetadataReviewTest.php`
