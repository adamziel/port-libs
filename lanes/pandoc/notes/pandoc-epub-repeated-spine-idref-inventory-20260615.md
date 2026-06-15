# Pandoc EPUB Repeated Spine Idref Inventory - 2026-06-15

## Scope

`EpubPackage` now reports repeated OPF spine `idref` groups in `readingOrderInventory` and mirrors the same review data into `wordpressImport`.

The report complements package validation: repeated spine occurrences continue to surface through the existing `duplicate-spine-itemref-idref` validation diagnostics, while `readingOrderInventory` preserves a review-scoped `repeated-spine-idref` summary for each repeated reading-order group.

## Accounting

- Base: current main `445e193d3`
- `phpPass`: `3709 -> 3710`
- Upstream mapped cases: `3732 -> 3733`
- `mappedEpubRepeatedSpineIdrefInventoryCases`: `0 -> 1`
- `epubRepeatedSpineIdrefInventoryAssertions`: `0 -> 34`

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` passed `1` file, `3190` assertions, `0` failures
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- `php tools/run-tests.php lanes/pandoc/tests` passed `46` files, `87892` assertions, `0` failures
- `jq empty` for lane status and upstream manifest JSON
- `git diff --check`
- Conflict-marker scan

No Pandoc binary, EPUBCheck, `zip`/`unzip`, `ZipArchive`, browser renderer, external validator, online service, live provider test, or live-service provider test is part of this slice.
