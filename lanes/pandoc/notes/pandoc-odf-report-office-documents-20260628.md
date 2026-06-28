# ODF report package office documents

Slice: `plib-f9q10`, ODF/ODT package ingestion.

`Reports/` sidecars now infer common office-document media types from package
paths in both the rich `OdfReader` path and compact `OpenDocumentPackage`
summary path. Declared-empty or undeclared `docx`, `xlsx`, `pptx`, `odg`,
`odf`, and `odc` report artifacts classify as `report-document` review items
instead of generic report outputs, while retaining the existing
`report-package-bytes-blocked` metadata-only exposure policy.

The focused fixture pins declared-empty `Reports/Quarterly/source.docx` and
undeclared `Reports/Quarterly/orphan.xlsx` across package report counts,
provenance roles, compact inventory parity, and WordPress block byte
non-exposure.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderReportPackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderReportPackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 2 files, 2,290 assertions, 0 failures.

No Pandoc, office suites, archive CLIs, browser renderers, live services, or
external validators were invoked.
