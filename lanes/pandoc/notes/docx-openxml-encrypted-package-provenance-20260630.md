# DOCX OpenXML encrypted package provenance - 2026-06-30

Implemented a bounded DOCX/OpenXML package-ingestion slice for package-root
`encrypted-package` relationships.

- `DocxOpenXmlReader` now promotes encrypted-package relationship targets into
  `packageProvenance.encryptedPackages` and `packageProvenance.summary`.
- Internal encrypted package targets carry metadata-only target, content-type,
  byte-length, CRC32, and SHA-256 provenance while bytes stay blocked under
  `encrypted-package-bytes-blocked`.
- External encrypted package targets are not fetched and retain the existing
  external-target allow/unsafe policy metadata.
- Package inventory and relationship-type rollups now classify encrypted package
  targets with the `encrypted-package` role.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 1 file, 13,176 assertions, 0 failures.
