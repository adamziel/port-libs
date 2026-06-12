# Pandoc EPUB3 Compact Rootfile Renditions

Slice: `plib-vt50o`, EPUB3 package ingestion core blocker.

Implemented bounded native PHP compact EPUB package rendition summary support in `EpubPackage`.

## Behavior

- Added `EpubPackage::renditions()` for OCF container rootfiles whose media type resolves to `application/oebps-package+xml`.
- The report preserves selected OPF path/index, alternate count, flattened diagnostics, rootfile media-type parameter provenance, ZIP entry byte/compression/CRC provenance, and per-rendition package/metadata summaries.
- Alternate OPF rootfiles are parsed only for bounded package-review metadata, rendition properties, manifest item count, and spine item count. Missing or malformed alternate rootfiles are reported as diagnostics and do not change the selected package handoff.
- `summary()` and `summary()['wordpressImport']` now expose the compact rendition report and diagnostics beside existing rootfile validation.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`: 1 test file, 1850 assertions, 0 failures
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 69273 assertions, 0 failures

No Pandoc, EPUBCheck, office suite, browser renderer, zip/unzip, Node tooling, external validator, online service, or live provider test was run.
