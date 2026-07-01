# DOCX OpenXML Package Identity Extension Case Variants

Slice: `plib-10pnu`

`DocxOpenXmlReader` now carries normalized package part extension case-variant
metadata into `packageProvenance.packageIdentity` and mirrors those identity
fields in `packageProvenance.summary`.

## Handoff

- `packageIdentity.partExtensionCaseVariantCount` and
  `packageIdentity.partExtensionCaseVariantExtensions` expose the normalized
  extension buckets whose raw package part extensions required case
  normalization.
- `packageIdentity.partExtensionUppercasePartCount` preserves the total number
  of package parts with uppercase raw extension characters.
- `summary.packageIdentityPartExtensionCaseVariant*` mirrors the compact
  identity profile so callers can compare package identities without walking
  the detailed raw-extension rows.

The slice is metadata-only package ingestion. It does not expose package payload
bytes beyond the existing bounded inventory sizes and hashes, and it does not
shell out to Pandoc, office tooling, zip/unzip, browsers, TeX, Node, or external
validators.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackagePartRawExtensionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartRawExtensionsTest.php` -> `1 test files, 69 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageIdentityTest.php` -> `1 test files, 477 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> `1 test files, 12508 assertions, 0 failures`

Direct-format parity remains active in `lane-status.json`; this slice adds
DOCX/OpenXML package-ingestion provenance and does not change the direct format
denominator.
