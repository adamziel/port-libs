# ODF Extension Package Invalid Declared Size

Slice: `plib-6qpsw`

ODF/ODT package ingestion now carries invalid `manifest:size` metadata for
`Extensions/` package sidecars through both native package paths:

- rich `OdfReader` `packageExtensions` rows expose `declaredSizeRaw`,
  `declaredSizeValid`, `declaredSizeInvalid`, `invalidDeclaredSizeCount`, and
  `odf-extension-package-invalid-declared-size`;
- compact `OpenDocumentPackage` `packageExtensions` rows carry matching
  declared-size provenance and issue counts;
- extension bytes remain blocked under `extension-package-bytes-blocked`, and
  manifest review rows continue to expose only metadata.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderExtensionPackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderExtensionPackageSidecarTest.php`
  passed with 180 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderExtensionPackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with 7,948 assertions and 0 failures.
