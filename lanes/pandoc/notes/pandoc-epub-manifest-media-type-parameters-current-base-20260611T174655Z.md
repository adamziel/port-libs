# EPUB manifest media-type parameter provenance

Slice: plib-bm049, Pandoc EPUB3 package ingestion core blocker, 2026-06-11T174655Z.

Base: current main 0f7efc602.

EpubPackage now preserves OPF manifest media-type parameter provenance for package review. Manifest items expose the raw media-type, normalized base media type, parsed parameter list/map, quoted parameter values, parameter counts, syntax validity, and diagnostics. Package validation aggregates parameter-bearing manifest items, unique parameter names, and manifest media-type diagnostics; WordPress import summaries mirror those review records. XHTML, CSS, and image classification now uses the base media type so MIME parameters do not hide navigation, spine, stylesheet, cover image, or asset summaries.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` passed: 1 test file, 1261 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 64551 assertions, 0 failures.

No Pandoc, EPUBCheck, zip/unzip, browser renderer, external validator, online service, live provider, or live-service provider test was invoked.
