# Pandoc PDF Extension Metadata Policy

Slice: PDF/Typst boundary provenance on current base `8a1306482`.

## Summary

- Added a bounded `pdfExtensionMetadataPolicy` summary beside the existing PDF
  catalog `/Extensions` byte metadata.
- The policy classifies extension dictionaries for review when the base version
  is missing, invalid, or above the effective PDF version, when extension levels
  are missing/nonpositive/high-boundary, or when extension prefixes duplicate.
- `fakeRunSequence()` now carries the final extension metadata policy through
  `finalPdfExtensionMetadataPolicy`.
- Expanded the existing PDF header/catalog/extensions fixture to cover a
  newer-than-effective base version and a missing base version with a high
  extension level.

## Direct-Format Accounting

- Added result fields: `pdfExtensionMetadataPolicy`,
  `finalPdfExtensionMetadataPolicy`.
- Added diagnostics: `pdf-byte-extension-policy:*`,
  `pdf-byte-extension-base-version:*`,
  `pdf-byte-extension-policy-issue:*`.
- Added assertions: `pdfExtensionMetadataPolicyAssertions = 10`.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 3631 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/YamlMetadataReviewTest.php`
  - 1 test file, 2 assertions, 2 failures
  - Existing broad-gate blocker: expected `title` metadata values resolve to
    tag/null values in YAML metadata review.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 295 test files, 116975 assertions, 9781 failures
  - Attempted broader lane gate; currently red outside this PDF slice. The
    visible isolated blocker above is in YAML metadata review.

No Pandoc, Typst, TeX engines, browser engines, external validators, online
services, live provider tests, or live-service provider tests were invoked.
