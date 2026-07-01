# Pandoc ODF Template Document Sidecars - 2026-06-30

Micro-slice: `plib-p4j9l-odf-template-document-sidecars-20260630`

## Behavior

- `Templates/` package sidecars now infer regular OpenDocument package document media types when the manifest omits `manifest:media-type`.
- Rich `OdfReader` and compact `OpenDocumentPackage` both classify `.odt`, `.odm`, `.ods`, `.odp`, `.odg`, `.odf`, and `.odc` template sidecars as metadata-only template documents.
- Inferred template document sidecars keep bytes blocked under `template-package-bytes-blocked`, remain out of document media and WordPress handoff, and still participate in package role, manifest review, and package identity counts.

## Evidence

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php`
  - Result: `1 test files, 125 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderFlatOpenDocumentSidecarTest.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php`
  - Result: `3 test files, 2311 assertions, 0 failures`

No Pandoc, office suite, TeX/browser engine, unzip/zip binary, external validator, or network service was invoked.
