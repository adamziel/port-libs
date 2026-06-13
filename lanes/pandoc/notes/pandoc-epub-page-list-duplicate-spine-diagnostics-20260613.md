# Pandoc EPUB Page-List Duplicate Spine Diagnostics

Date: 2026-06-13 UTC
Bead: plib-sjqr4

## Scope

This slice extends the compact native PHP `EpubPackageReader` package path with
`epub.tocReport` page-list diagnostics while preserving the existing `epub.toc`
entry shape. Page-list targets are reconciled against manifest paths and spine
reading-order metadata, including repeated page-list href targets, duplicate
spine `idref` itemrefs, nonlinear spine targets, and missing manifest targets.

The implementation remains metadata-only for direct EPUB package ingestion. It
does not invoke Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers,
Node, online services, external validators, or live-service provider tests.

## Evidence

- `EpubPackageReaderTest.php` now includes focused coverage for stable
  page-list report diagnostics and duplicate-spine/page-target ordering.
- `spineReport` exposes duplicate `idref` diagnostics without dropping duplicate
  readable spine entries.
- `tocReport.pageList` exposes manifest, reading-order, duplicate page-target,
  duplicate spine-target, nonlinear, and missing-manifest counters.

## Verification

```sh
php -l lanes/pandoc/src/EpubPackageReader.php
php -l lanes/pandoc/tests/EpubPackageReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php
php tools/run-tests.php lanes/pandoc/tests
git diff --check
```

Focused result: `EpubPackageReaderTest.php` passed 1 file, 275 assertions, 0
failures.

Full Pandoc result: `lanes/pandoc/tests` passed 45 files, 75,503 assertions, 0
failures.
