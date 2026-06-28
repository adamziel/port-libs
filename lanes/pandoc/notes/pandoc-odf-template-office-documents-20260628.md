# ODF template package office documents

Slice: `plib-hu4m5` (`2026-06-28`).

## Scope

- `Templates/` package sidecars now infer OpenXML template media types for
  `.dotx`, `.xltx`, and `.potx` package members in both `OdfReader` and
  `OpenDocumentPackage`.
- Declared-empty and undeclared OpenXML template members classify as
  `template-document`, matching the existing ODF template behavior.
- Template payload bytes remain metadata-only under
  `template-package-bytes-blocked` and stay out of document media/WordPress
  handoff.

## Direct-Format Accounting

- Extended the ODF template package sidecar fixture from 7 to 9 template items.
- This does not add a new upstream mapped case; it tightens package sidecar
  role/media inference and focused assertion evidence.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php`
  - 1 test file, 113 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderReportPackageSidecarTest.php lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php`
  - 4 test files, 2,525 assertions, 0 failures

No Pandoc, office suites, TeX/browser engines, unzip/zip commands, Jupyter,
Node tooling, external validators, or online services were invoked.
