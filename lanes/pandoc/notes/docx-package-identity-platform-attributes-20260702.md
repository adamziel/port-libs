# DOCX package identity platform attributes

Bead: `plib-y57h4`

## Summary

This slice carries existing DOCX ZIP platform attribute provenance into the
deterministic package identity surface.

- `DocxOpenXmlReader::packageIdentityProvenance()` now records metadata-only
  creator host/version, Unix mode and permission flags, DOS attributes, internal
  file attributes, platform issue codes, and review policy on each identity
  package entry.
- `packageProvenance.summary` now mirrors package-identity ZIP platform
  attribute counts and issue-code rollups.
- The identity remains metadata-only and does not expose package part bytes,
  XML payloads, media bytes, or external targets.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 16,017 assertions, 0 failures

## Metric Delta

- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2469 -> 2470`
- `mappedDocxPackageIdentityPlatformAttributeProvenanceCases`: `1`
- `docxPackageIdentityPlatformAttributeProvenanceAssertions`: `27`
- `lane-status.json` `phpPass`: `489 -> 490`
