# Pandoc Shared ZIP Package Core Current Base 2026-06-10T18:19:17Z

Slice: raw central-directory DOS attribute provenance for shared ZIP/OPC package handoff.

## Change

`ZipPackage::dosAttributePolicyPreflight()` now scans central-directory DOS
attribute metadata before package instantiation. Raw strict import preflight
includes the summary under `dosAttributes`, so hidden, system, and volume-label
entries remain visible for DOCX/EPUB/ODF reviewer queues even when a separate
raw package blocker, such as strong-encryption flags, prevents
`ZipPackage::fromString()`.

The scanner keeps read-only, archive, and directory bits as metadata, while
blocking hidden/system/volume-label entries with
`hidden-system-or-volume-label-entries` diagnostics.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 3009 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 60988 assertions, 0 failures`

## Accounting

- Focused ZIP package support adds 1 PHP PASS case.
- `phpPass` moves `2996 -> 2997`; `phpFail` remains `0`.
- Focused ZIP assertions move to 3009.

No Pandoc, Cabal/Haskell runner, office suite, zip/unzip, ZipArchive, browser
renderer, external validator, online service, live provider test, or
live-service provider test was executed.
