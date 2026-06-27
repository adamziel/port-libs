# ODF/ODT Template Package Sidecars

Slice: `pandoc-odf-template-package-sidecars`

This slice adds metadata-only package provenance for ODF `Templates/` package parts.

- Classifies `Templates/` entries as `template-package` parts in compact `OpenDocumentPackage` inventory/identity and rich `OdfReader` package provenance.
- Adds `packageTemplates` summaries with declared, missing, encrypted, undeclared, kind, group, byte-length, CRC, and issue-code review metadata.
- Keeps template document packages, template preview images, and undeclared template bytes out of document media and WordPress handoff.
- Blocks template sidecar bytes under `template-package-bytes-blocked` while retaining `directory-entry-no-bytes` and `encrypted-resource-bytes-blocked` for directory/encrypted entries.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php`
  - `1 test files, 113 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderGalleryPackageSidecarTest.php lanes/pandoc/tests/OdfReaderVersionPackageSidecarTest.php lanes/pandoc/tests/OdfReaderDatabasePackageSidecarTest.php`
  - `3 test files, 300 assertions, 0 failures`

No Pandoc, office suites, TeX engines, browser renderers, zip/unzip shell-outs, external validators, or online services were invoked.

Scope boundary:

This is package ingestion and metadata review only. It does not convert, render, validate, or expose template package payloads as document content.
