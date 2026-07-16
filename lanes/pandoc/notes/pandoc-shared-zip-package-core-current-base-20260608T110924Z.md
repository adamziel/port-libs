# Pandoc ZIP Package Core Current-Base Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-shared-zip-package-core-current-base-20260608T110924Z`
- Base accepted HEAD: `5fdecc98c514fd489c66940ca59605872f7dcf63`
- Scope: native PHP ZIP/OPC package primitive support under `lanes/pandoc/**`

## Behavior

Added `ZipPackage::localHeaderOrderPreflight()` to expose central-directory order versus physical local-header order before Pandoc-style package handoff. The summary reports central order names, local header order names, per-entry central index, local order, local offset, and mismatched entries.

`strictImportPreflight()` now embeds this provenance under `localHeaderOrder` without rejecting otherwise valid reordered ZIP packages. ZIP permits central-directory order to differ from local header order, while ODT/EPUB/package readers still need physical local-header order for checks such as stored-first `mimetype` entries.

The WordPress ZIP preflight example now prints and self-tests both the generated matching-order package and a valid reordered review package.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 1426 assertions, 0 failures`
- Focused final: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 1453 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` -> `zip package writer preflight self-test passed`
- PHP lint: `php -l lanes/pandoc/src/ZipPackage.php`, `php -l lanes/pandoc/tests/ZipPackageTest.php`, and `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` passed
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc` passed

## Status Delta

- PHP PASS: `1624 -> 1625`
- Focused ZIP test assertions: `1426 -> 1453` (`+27`)
- Mapped denominator: `2043 -> 2044`
- `zipPackageCoreSupportCases`: `22 -> 23`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 188`

## Non-Overlap

This slice does not repeat ZIP64 package/entry rejection, Unix symlink or special-file rejection, drive-letter/unsafe path handling, Unicode-normalized name collisions, central-directory digital signatures, invalid DOS timestamps, directory CRC/payload checks, duplicate central/local extra fields, central/local extra-field id/value mismatches, data-descriptor integrity, unsupported compression method policy, or trailing raw-deflate byte validation. It is limited to central-directory order versus physical local-header order provenance.

## Dependency Closure

No new native PHP support component is needed. The slice reuses the existing `ZipPackage` central-directory parser, local-header span validation, strict import preflight, focused ZIP fixture builder, and WordPress ZIP preflight example. No Pandoc, Cabal/Haskell runner, `zip`, `unzip`, `ZipArchive`, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.
