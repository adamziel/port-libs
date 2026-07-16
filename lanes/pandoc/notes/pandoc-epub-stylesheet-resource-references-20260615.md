# EPUB Stylesheet Resource References

Slice: `plib-wdlru`, EPUB3 package ingestion.

`EpubPackage` now preflights manifest `text/css` stylesheets for `@import`
and `url()` references and exposes those dependencies in compact package review
packets. The summary classifies local manifest hits, external references,
data URIs, missing package parts, unmanifested package entries, unsupported
compression, and encrypted/obfuscated font byte-blocking without exposing
payload bytes.

The surface is available through `stylesheetResources()`,
`compactPackageReport()`, and WordPress import review packets.

This stays under `lanes/pandoc` and does not invoke Pandoc, EPUBCheck,
zip/unzip, ZipArchive, browser renderers, CSS engines, external validators,
online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 3524 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 55 files, 93718 assertions, 0 failures

Accounting:

- `phpPass`: `4447 -> 4448`
- `phpFail`: `0`
- upstream mapped cases: `4437 -> 4438`
- `mappedEpubStylesheetResourceReferenceCases`: `1`
- `epubStylesheetResourceReferenceAssertions`: `56`

Non-overlap:

This does not validate CSS, render stylesheets, fetch remote assets, or parse
XHTML presentation semantics. The new surface is only package-local stylesheet
dependency preflight for compact EPUB ZIP/OPF ingestion review.
