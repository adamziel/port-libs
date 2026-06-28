# ODF report package media precedence

Slice: `plib-yyle3`, ODF/ODT package ingestion.

The report package sidecar regression now pins `Reports/` package outputs that
look like media or export resources in `mediaResources.packageRolePrecedence`.
Rich `OdfReader` and compact `OpenDocumentPackage` both keep
`Reports/Quarterly/preview.png`, `report.odt`, and PDF report outputs under
the stronger `report-package` role, with `report-package-bytes-blocked`, rather
than exposing them through document media or WordPress handoff.

Validation:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderReportPackageSidecarTest.php lanes/pandoc/tests/OdfReaderEventPackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
- Result: 3 files, 2,345 assertions, 0 failures.

This is a focused package-review guard only. It does not add new document
rendering, office-suite execution, external validators, PDF parsing, or report
payload byte exposure.
