# ODF/OpenDocument Manifest Declared Size Review

Slice: `pandoc-odf-open-document-core-current-base-20260610T110712Z`

## What Changed

Native `OdfReader` package ingestion now compares non-encrypted manifest
`manifest:size` declarations against the ZIP local package part size and carries
declared-size mismatch metadata through the manifest and media review reports.
The importer keeps rendering content, but exposes the discrepancy for
WordPress/package review instead of silently treating the declared size as
trusted.

This is a bounded ODF/ODT package-ingestion slice. It does not invoke Pandoc,
office suites, zip/unzip, browser renderers, external validators, online
services, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 3451 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 59569 assertions, 0 failures`

## Accounting

- `phpPass`: `2952 -> 2953`
- `phpFail`: `0`
- Focused ODF reader coverage adds one PASS case for manifest declared-size
  mismatch metadata.

## Scope Notes

Encrypted ODF package entries are intentionally excluded from mismatch
classification because ZIP entry sizes describe encrypted payload bytes rather
than the original cleartext resource. Missing resources continue to be reported
through the existing missing-item path.
