# ODF dictionary package invalid declared-size provenance

Slice: `plib-wyvu2` (`2026-06-30`).

## Scope

- Dictionary package sidecars now preserve malformed `manifest:size`
  provenance in both rich `OdfReader` package metadata and compact
  `OpenDocumentPackage` summaries.
- `Dictionaries/` review items now carry `declaredSizeRaw`,
  `declaredSizeValid`, `declaredSizeInvalid`, `invalidDeclaredSizeCount`, and
  `odf-dictionary-package-invalid-declared-size` while keeping dictionary bytes
  blocked under `dictionary-package-bytes-blocked`.
- The focused dictionary sidecar fixture exercises a malformed `.dic`
  declared size and verifies rich/compact parity without exposing dictionary
  bytes through document media or WordPress output.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php`
  - 1 test file, 130 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - 2 test files, 7,768 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php`
  - 3 test files, 430 assertions, 0 failures

No Pandoc, office suites, TeX/browser engines, unzip/zip commands, Jupyter,
Node tooling, external validators, or online services were invoked.
