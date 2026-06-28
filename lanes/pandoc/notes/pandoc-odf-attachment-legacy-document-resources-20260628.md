# Pandoc ODF Attachment Legacy Document Resources

Slice: `plib-6qpsw`

Implemented a bounded ODF/ODT package-ingestion slice for `Attachments/`
sidecars:

- `OdfReader` and `OpenDocumentPackage` now infer legacy Office, RTF, and
  ODF master/template attachment media types from package paths.
- Those attachments are classified as `attachment-document-resource` review
  rows while keeping bytes blocked under `attachment-package-bytes-blocked`.
- Declared RTF and undeclared DOC attachment regressions now verify rich reader
  parity, compact package parity, package identity role counts, and WordPress
  non-exposure.

No external Pandoc, office suite, zip/unzip, browser, or validator was invoked.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `2 test files, 2260 assertions, 0 failures`

Parity accounting:

- `mappedOdfAttachmentLegacyDocumentResourceCases`: `1`
- `odfAttachmentLegacyDocumentResourceAssertions`: `19`
- ODF/ODT readiness local mapped cases: `90 -> 91`
