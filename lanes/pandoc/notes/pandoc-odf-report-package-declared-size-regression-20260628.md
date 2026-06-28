# pandoc-odf-report-package-declared-size-regression-20260628

Slice: `plib-t0jqy`

This slice repairs the focused ODF/ODT report package sidecar regression for
invalid `manifest:size` provenance. The report package test already exercised
metadata-only `Reports/` sidecars with invalid declared-size review fields, but
the closure did not capture `$exportSize`, so the raw-size assertion compared
against `bytes` instead of the expected `<size>bytes` value.

The fix keeps the existing native package-ingestion behavior unchanged:

- rich `OdfReader` report-package rows preserve `declaredSizeRaw`,
  `declaredSizeValid`, `declaredSizeInvalid`, and
  `odf-report-package-invalid-declared-size`;
- compact `OpenDocumentPackage` report-package rows preserve the same
  invalid-size provenance;
- report sidecar bytes remain blocked under `report-package-bytes-blocked` and
  stay out of media and WordPress handoff.

No direct-format denominator movement is claimed; this repairs an existing
mapped ODF/ODT package-ingestion regression.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderReportPackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderReportPackageSidecarTest.php`
  - passed: 1 file, 131 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderReportPackageSidecarTest.php lanes/pandoc/tests/OdfReaderEventPackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - passed: 3 files, 2,359 assertions, 0 failures
