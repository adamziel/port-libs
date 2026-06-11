# Pandoc EPUB3 Package Accessibility Metadata Current Base

Slice: `pandoc-epub3-package-accessibility-metadata-current-base-20260611T130116Z`

## Summary

`EpubPackage` compact package ingestion now normalizes OPF accessibility metadata for package review. The report captures schema/a11y access-mode, access-mode-sufficient, feature, hazard, summary, certification, and conforms-to metadata, plus OPF linked accessibility records with inert ZIP provenance.

The compact summary now exposes this report at:

- `summary()['accessibility']`
- `summary()['wordpressImport']['accessibility']`
- `metadata()['accessibility']`

## Scope

This is bounded to native PHP OPF metadata and link-report handoff. It reuses existing ZIP package parsing and OPF metadata link provenance. It does not invoke Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online services, or live provider tests.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - Result: `1 test files, 1073 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 63118 assertions, 0 failures`

## Metric

Adds one focused EPUB package test case:

- `phpPass`: `3061 -> 3062`
- `phpFail`: `0`
