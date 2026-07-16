# Pandoc rich package extension denominator slice 2026-06-10T104100Z

## Summary

Mapped one bounded native registry case for rich package unsupported-format
extension diagnostics.

- `RichPackageUnsupportedFormatRegistry` now reports the current 12-format
  rich package denominator: DOCX, ODT, OpenDocument, EPUB/EPUB2/EPUB3, IPYNB,
  PPTX, XLSX, chunked HTML, ICML, and PDF.
- Added extension-level status rows for `.docx`, `.epub`, `.fodt`, `.icml`,
  `.ipynb`, `.odt`, `.pdf`, `.pptx`, and `.xlsx`.
- Keeps native input support bounded to existing readers while exposing
  unsupported writer/package directions for notebook, PDF, ICML, flat
  OpenDocument, EPUB variants, Office packages, and chunked HTML.
- No converter, renderer, notebook, office, zip/unzip, or external validator
  path is registered or invoked.

## Verification

- `php -l lanes/pandoc/src/RichPackageUnsupportedFormatRegistry.php`
- `php -l lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`
  - Result: `1 test files, 101 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 59471 assertions, 0 failures`.

## Metric Delta

- `lane-status.json` `phpPass`: `2951 -> 2952`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3123 -> 3124`
- `mappedPandocRichPackageUnsupportedFormatCases`: `2 -> 3`
- `pandocRichPackageUnsupportedFormatAssertions`: `89 -> 101`
