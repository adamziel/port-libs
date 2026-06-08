# pandoc-shared-zip-package-core-current-base-20260608T183655Z

## Behavior

- Added native ZIP permission preflight coverage for Unix group-writable and world-writable entries before DOCX/EPUB/ODT/Office media handoff.
- `ZipPackage::permissionPreflight()` now reports group/world writable counts, writable entry details, and per-entry issue tags while preserving executable-file reporting.
- `ZipPackage::assertNoWritablePermissionEntries()` rejects group/world-writable entries, and strict import preflight now emits `unix-writable-permission-entries`.
- `wordpress-zip-package-preflight.php --self-test` now exercises the writable-permission rejection path.

## Evidence

- Baseline before patch: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 1599 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 1647 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` -> `zip package writer preflight self-test passed`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted ZIP central-directory signature, invalid DOS timestamp, trailing-deflate, data-descriptor, Unicode-name collision, Zip64, NTFS timestamp, symlink, Unix special-file, DOS hidden/system/volume-label, or local-header provenance work. It only adds Unix group/world-writable permission diagnostics and strict import rejection.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP ZIP central-directory metadata, Unix mode extraction, strict preflight aggregation, and WordPress package-preflight smoke infrastructure. No Pandoc, Cabal/Haskell runner, zip/unzip, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Follow-Up

For the next ZIP package slice, choose a non-overlapping native package preflight gap such as local-header/platform metadata, OPC/EPUB/ODT package boundary semantics, or additional bounded archive provenance.
