# Pandoc ZIP Extra-Field Byte Footprint Slice 2026-06-11

Slice: `pandoc-zip-extra-field-byte-footprint-20260611T185842Z`, based on current main `f99ec6e05`.

## Scope

This slice stays inside `lanes/pandoc` and covers shared ZIP/OPC package primitives. It makes ZIP extra-field byte footprint explicit for package review so central-directory and local-header metadata overhead can be compared without inferring from raw ZIP bytes.

## Implementation

- `ZipPackage::extraFieldPreflight()` now reports central/local extra-field record and data byte totals per package and per entry.
- `ZipPackage::extraFieldPolicyPreflight()` exposes the same byte totals before package instantiation.
- `extraFieldIdUsage` rows now include central/local record bytes, data bytes, and combined totals per extra-field ID.
- Added focused `ZipPackageTest` coverage for object, raw policy, strict, and raw strict preflight paths.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 3208 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 65369 assertions, 0 failures`

No Pandoc, office suite, TeX/browser engine, `zip`/`unzip`, external validator, online service, live provider test, or live-service provider test was executed.

## Direct-Format Parity Accounting

- Added one focused shared ZIP/OPC package PASS case with 25 assertions.
- Lane status `phpPass` moves `3099 -> 3100`; `phpFail` remains `0`.
