# ODF template package office documents

Slice: `plib-hu4m5` (`2026-06-28`).

Follow-up: `plib-fskan` (`2026-06-28`).

## Scope

- `Templates/` package sidecars now infer OpenXML template media types for
  `.dotx`, `.xltx`, and `.potx` package members in both `OdfReader` and
  `OpenDocumentPackage`.
- Declared-empty and undeclared OpenXML template members classify as
  `template-document`, matching the existing ODF template behavior.
- Template payload bytes remain metadata-only under
  `template-package-bytes-blocked` and stay out of document media/WordPress
  handoff.
- Follow-up coverage extends the same rich/compact path to ODF
  chart/image/formula templates (`.otc`, `.oti`, `.otf`), macro-enabled Office
  templates (`.dotm`, `.xltm`, `.potm`), and legacy Office template extensions
  (`.dot`, `.xlt`, `.pot`).

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
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 2 test files, 2,296 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderReportPackageSidecarTest.php lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php`
  - 4 test files, 2,589 assertions, 0 failures

No Pandoc, office suites, TeX/browser engines, unzip/zip commands, Jupyter,
Node tooling, external validators, or online services were invoked.
