# ODF Script Package Invalid Declared Size

Slice: `plib-649yx`

ODF/ODT package ingestion now carries invalid `manifest:size` metadata for
macro/script/dialog package sidecars through both native package paths:

- rich `OdfReader` `packageScripts` rows expose `declaredSizeRaw`,
  `declaredSizeValid`, `declaredSizeInvalid`, `invalidDeclaredSizeCount`, and
  `odf-script-invalid-declared-size`;
- rich runtime `scriptMetadata` rows expose the same declared-size provenance
  and `odf-script-package-invalid-declared-size`;
- compact `OpenDocumentPackage` `packageScripts` rows carry matching
  declared-size provenance and issue counts;
- script bytes remain blocked under `script-package-bytes-blocked`, and manifest
  review rows continue to expose only metadata.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php`
  passed with 326 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed with 2,205 assertions and 0 failures.
- Adjacent ODF gate
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with 7,801 assertions and 0 failures.
