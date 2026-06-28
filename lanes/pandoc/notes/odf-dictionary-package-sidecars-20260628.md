# ODF dictionary package sidecars

Slice: `plib-9g93z` (`2026-06-28`).

## Scope

- Added metadata-only `Dictionaries/` package sidecar handling for rich ODF reads
  and compact OpenDocument package summaries.
- Dictionary package parts now carry `dictionary-package` roles, manifest/package
  identity flags, byte exposure policy `dictionary-package-bytes-blocked`, and
  review family `dictionary`.
- Dictionary sidecars are excluded from document media byte handoff while
  preserving declared, undeclared, missing, encrypted, directory, kind, group,
  CRC, size, and compression metadata for review.

## Direct-Format Accounting

- Added focused ODT dictionary package sidecar fixture/test:
  `OdfReaderDictionaryPackageSidecarTest.php`.
- Added coverage for 8 dictionary sidecar review items:
  directory, manifest XML, `.dic`, `.aff`, preview media, missing part,
  encrypted part, and undeclared ZIP part.
- Focused case: 111 assertions.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php`
  - 1 test file, 111 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php`
  - 3 test files, 313 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 1931 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php`
  - 2 test files, 126 assertions, 0 failures

No Pandoc, office suites, TeX/browser engines, unzip/zip commands, Jupyter,
Node tooling, external validators, or online services were invoked.
