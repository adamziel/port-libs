# DOCX package artifact inventory roles

Slice: `plib-5jd43`, DOCX/OpenXML package ingestion provenance.

`DocxOpenXmlReader` now classifies package-level thumbnail and digital
signature artifacts with dedicated inventory roles:

- `package-thumbnail`
- `digital-signature-origin`
- `digital-signature-signature`

The roles flow through package part inventory, aggregate role counts and
byte lengths, and relationship-type target-role buckets. Existing
thumbnail and digital-signature provenance remains metadata-only: package
thumbnail bytes are not exposed as document media, signature XML bytes are
not exposed as document media, and signature records are not
cryptographically validated.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageInventoryRolesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageInventoryRolesTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 2 test files, 10,528 assertions, and 0 failures.

No Pandoc, office suites, TeX/PDF engines, browser engines, archive tools,
Node tooling, external validators, or online services were invoked.
