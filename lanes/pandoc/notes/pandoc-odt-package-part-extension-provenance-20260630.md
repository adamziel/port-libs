# ODF/ODT Package Part Extension Provenance

Slice: `plib-aj7ac`

`OdfReader` now carries package-part extension metadata through rich ODT package
provenance and metadata-only package identity:

- `packagePartExtension`, `rawPackagePartExtension`,
  `packagePartExtensionHasUppercase`, `packagePartExtensionWasNormalized`, and
  `extensionlessPackagePart` are recorded on each package part.
- `packagePartExtensionCounts`, `entryNamesByPackagePartExtension`,
  `extensionlessPackagePartCount`, and `packagePartExtensionSummaries` aggregate
  declared, undeclared, encrypted, exposable, blocked, sidecar, and uppercase
  extension-normalization buckets.
- The rich reader now matches the compact `OpenDocumentPackage` package
  extension inventory shape without reading blocked bytes.

This stays inside `lanes/pandoc`, uses native PHP ZIP/XML package metadata, and
does not invoke Pandoc, office suites, `zip`/`unzip`, external validators,
browser engines, TeX, Node tooling, or network fetches.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php`
  - `1 test files, 53 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `3 test files, 2001 assertions, 0 failures`

Accounting:

- `lane-status.json` `phpPass`: `469 -> 470`
- Direct-format parity remains active; this is metadata-only ODT package
  ingestion coverage and does not claim external Pandoc parity.
