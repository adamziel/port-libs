# Shared ZIP central-directory inventory byte spans

## Scope

- Slice: `pandoc-shared-zip-central-directory-inventory-byte-spans`
- Base: current main `931ef03303`
- Area: shared ZIP/OPC package inventory before DOCX, EPUB, and ODF readers expose package bytes.

## Change

- `ZipPackage::centralDirectoryInventoryPreflight()` now carries aggregate central-directory entry record, fixed-header, variable-field, raw-name, extra-field, and raw-comment byte buckets.
- Inventory entries now expose their record offset/length, fixed-header span, variable-field span, raw-name span, central extra-field span, raw-comment span, and record end.
- Raw strict import and instantiated strict package reviews inherit the expanded inventory packet before package-specific readers hand off payload bytes.

## Direct-format parity accounting

- `phpPass` moves `3508 -> 3509`; `phpFail` remains `0`.
- `mappedZipCentralDirectoryInventoryByteSpanCases` moves `0 -> 1`.
- `zipCentralDirectoryInventoryByteSpanAssertions` moves `0 -> 30`.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed `1 test files, 4726 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests` passed `46 test files, 82714 assertions, 0 failures` after rebase verification.

No Pandoc, office suites, TeX/PDF engines, browser renderers, `zip`/`unzip`, `ZipArchive`, external validators, online services, live provider tests, or live-service provider tests were invoked.
