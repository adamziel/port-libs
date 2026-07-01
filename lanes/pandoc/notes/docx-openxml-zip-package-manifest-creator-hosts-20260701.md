# DOCX OpenXML ZIP Package Manifest Creator Hosts

## Slice

- Surface ZIP package manifest creator host-system and creator-version aggregates through DOCX package ingestion.
- The nested `packageProvenance.zipPackage` record now carries `packageManifestCreator*` and host-system review fields.
- The top-level `packageProvenance.summary` now carries matching `zipPackageManifestCreator*` and host-system review fields.

## Fixture

- Extended the existing DOCX source ZIP platform-attribute fixture.
- The fixture includes the default Unix host plus a Windows NTFS creator host entry.
- Assertions verify the package manifest host buckets and creator-version counts survive into summary provenance.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
