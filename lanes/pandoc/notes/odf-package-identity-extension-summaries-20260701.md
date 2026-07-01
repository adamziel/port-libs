# ODF Package Identity Extension Summaries

Slice: `odf-package-identity-extension-summaries-20260701`

## Scope

- `OpenDocumentPackage` package identity metadata now carries the package-part extension summary count, per-extension entry-name buckets, and full extension summary records already computed by package inventory.
- `OdfReader` rich package identity now carries the same extension summary fields and preserves manifest media-family buckets while building those summaries.
- The projection is metadata-only and keeps compact `OpenDocumentPackage` summaries aligned with rich `OdfReader` package provenance and the document-level package-provenance identity attribute.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageIdentityExtensionSummariesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageIdentityExtensionSummariesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageIdentityExtensionSummariesTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
- `git diff --check`
