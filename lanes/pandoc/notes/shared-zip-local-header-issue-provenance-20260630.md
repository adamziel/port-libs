# Shared ZIP local header issue provenance

Hook: `plib-kgy5q`, Pandoc shared ZIP/OPC package core blocker slice 20260615T103808Z.

Implemented a bounded metadata-only ZIP package preflight slice.
`ZipPackage::localHeaderSpanPreflight()` and
`ZipPackage::packageByteLayoutPreflight()` now summarize layout issues by
issue count and affected entry name before package handoff, so DOCX/ODT/EPUB
and shared OPC callers can consume local-header span diagnostics without
scraping per-entry issue arrays or exposing package bytes.

The focused regression builds ZIP bytes directly in PHP with one deflated DOCX
part followed by unclaimed local-entry bytes that look like an archive extra
data record. It verifies matching aggregate provenance through local-header
span preflight, package byte-layout preflight, and raw strict import preflight
before package instantiation is allowed.

This is native PHP ZIP/OPC package metadata support only. It does not invoke
Pandoc, Haskell/Cabal runners, office suites, zip/unzip, ZipArchive, TeX/PDF
engines, browser renderers, Jupyter, online services, live provider tests, or
external validators. Direct-format parity remains tracked in the lane blocker;
this slice narrows shared package-core diagnostics for existing direct package
formats without claiming new Pandoc format parity.

Accounting:

- `lane-status.json` `phpPass`: `469 -> 470`.
- Added `ZipPackageLocalHeaderIssueProvenanceTest.php` with one focused case.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageLocalHeaderIssueProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageLocalHeaderIssueProvenanceTest.php`: 1 file, 20 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`: 1 file, 4,884 assertions, 0 failures.
