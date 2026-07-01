# ODF package document-part provenance

Hook: `plib-yyle3`, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice.

## Slice

`OpenDocumentPackage::summarize()` now exposes a metadata-only
`documentParts` package preflight for the core ODT XML package parts:
`content.xml`, `styles.xml`, `meta.xml`, and `settings.xml`.

The report preserves manifest linkage, accepted root names, actual root names,
`office:version` values, version mismatch and missing-version diagnostics, ZIP
byte/compression metadata, and root custom attribute provenance while keeping
document-part bytes blocked under
`odf-document-part-package-provenance-metadata-only`.

## Direct parity accounting

- `OpenDocumentPackageTest.php` mapped cases: +1
- `OpenDocumentPackageTest.php` assertions: 1898 -> 1931
- `phpFail`: 0

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 1931 assertions, 0 failures

No Pandoc executable, office suites, TeX/browser engines, zip/unzip commands,
Jupyter, Node tooling, external validators, online services, or live provider
tests were invoked.
