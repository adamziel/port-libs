# DOCX/OpenXML Package XML Root Attribute Provenance

Slice: `plib-mke69`, DOCX OpenXML package ingestion core blocker.

This slice extends `DocxOpenXmlReader` package provenance for XML-inspectable
DOCX package parts. Package inventory rows and `packageProvenance.summary` now
carry value-free XML root attribute metadata:

- per-part root attribute records with qualified name, local name, prefix, and
  namespace;
- package-wide root attribute name, local-name, prefix, and namespace buckets;
- root-attribute-bearing part names.

Attribute values and package bytes remain out of the aggregate review surface.
No upstream Pandoc, office suite, `zip`/`unzip`, browser, network, or external
validator was invoked.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 1 file, 10,523 assertions, 0 failures.

Parity accounting:

- `lane-status.json` `phpPass`: `464 -> 465`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2307 -> 2308`
- Added `mappedDocxPackageXmlRootAttributeCases: 1`
