# Pandoc EPUB Package Link Media-Type Parameters

Date: 2026-06-15
Slice: `pandoc-epub-package-link-media-type-parameters`
Target: current `origin/main` `018189c5fd`

## Summary

This slice maps one compact EPUB3 package-ingestion blocker case for OPF
metadata `<link>` media-type parameter provenance. `EpubPackage` now keeps the
declared link media type and, when omitted, inherits the matched manifest
item media type for package-link review packets.

The compact handoff now exposes:

- declared and effective package-link media types
- media-type source (`link` or `manifest`)
- normalized MIME and base MIME values
- parameter records, parameter maps, and sorted parameter names
- invalid parameter and duplicate parameter diagnostics scoped to package links
- aggregate `packageLinkMediaTypes` summary data
- WordPress import fields for package-link media-type review

## Files

- `lanes/pandoc/src/EpubPackage.php`
- `lanes/pandoc/tests/EpubPackageTest.php`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 2877 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 86785 assertions, 0 failures
- PHP JSON decode validation for lane status and upstream manifest
- `git diff --check`
- conflict-marker scan over touched Pandoc lane files

## Accounting

- `phpPass`: 3677 -> 3678
- `phpFail`: 0
- upstream mapped cases: 3709 -> 3710
- `mappedEpubPackageLinkMediaTypeParameterCases`: 1
- `epubPackageLinkMediaTypeParameterAssertions`: 39

## Non-Overlap

Earlier EPUB media-type parameter work covered manifest items, guide targets,
container rootfiles, and the older `EpubReader` metadata-link path. This slice
is limited to the compact `EpubPackage` OPF metadata-link package-ingestion
handoff and does not invoke Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.
