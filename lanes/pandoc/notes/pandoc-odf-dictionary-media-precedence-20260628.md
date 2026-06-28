# ODF dictionary media precedence

Slice: `plib-zcw3q` (`2026-06-28`).

## Scope

- Compact `OpenDocumentPackage` media-resource review now treats image-like
  `Dictionaries/` package previews as `dictionary-package` precedence items.
- Dictionary preview bytes remain blocked under
  `dictionary-package-bytes-blocked` and stay out of document media handoff.
- The behavior now matches the rich `OdfReader` package precedence helper, which
  already recognized dictionary sidecars.

## Direct-Format Accounting

- Extended the compact ODT media-resource sidecar precedence fixture from 5 to
  6 package-role precedence items.
- Added explicit coverage for `Dictionaries/en_US/preview.png` as a
  metadata-only dictionary package preview.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 1,966 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php`
  - 1 test file, 111 assertions, 0 failures
- ODF package sidecar/identity gate:
  `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php lanes/pandoc/tests/OdfReaderLinkedResourcePackageSidecarTest.php lanes/pandoc/tests/OdfReaderFormPackageSidecarTest.php lanes/pandoc/tests/OdfReaderGalleryPackageSidecarTest.php lanes/pandoc/tests/OdfReaderDatabasePackageSidecarTest.php lanes/pandoc/tests/OdfReaderSignatureCertificatePackageTest.php lanes/pandoc/tests/OdfReaderMetaInfSidecarTest.php lanes/pandoc/tests/OdfReaderFontPackageBytePolicyTest.php lanes/pandoc/tests/OdfReaderVersionPackageSidecarTest.php lanes/pandoc/tests/OdfReaderStylePackageProvenanceTest.php lanes/pandoc/tests/OdfReaderCorePackageHandoffPreflightTest.php lanes/pandoc/tests/OdfDialogPackageSidecarTest.php`
  - 17 test files, 3,416 assertions, 0 failures
- Broad `php tools/run-tests.php lanes/pandoc/tests` was attempted
  post-rebase and is red on unrelated `YamlMetadataReviewTest.php` failures on
  this base, so this slice uses the ODF/package-focused gate above.

No Pandoc, office suites, TeX/browser engines, unzip/zip commands, Jupyter,
Node tooling, external validators, or online services were invoked.
