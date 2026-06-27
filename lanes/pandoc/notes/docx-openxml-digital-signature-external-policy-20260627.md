# DOCX OpenXML Digital Signature External Policy

Slice: `plib-mrlie`, DOCX/OpenXML package ingestion, 2026-06-27.

`DocxOpenXmlReader` now carries external-target policy metadata for package
digital-signature origin relationships and origin-sourced signature
relationships. The package provenance distinguishes allowed HTTPS review links
from unsafe file targets with per-origin/per-signature counters, unsafe target
lists, and external issue codes.

External signature targets remain metadata-only: no external signature target is
fetched, no signature bytes are exposed as document media, and no cryptographic
validation is claimed.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 1 file, 10,167 assertions, and 0 failures.

Metric movement:

- `lanes/pandoc/lane-status.json` `phpPass`: 457 -> 458
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: 2303 -> 2304
- Added `mappedDocxDigitalSignatureExternalTargetPolicyCases = 1`
