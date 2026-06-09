# ZIP Package Central Directory Count Direction

Slice: `pandoc-shared-zip-package-core-current-base-20260609T022509Z`
Base: `7afabee2a7f5893f638a62223e4bc28f1e89fcb0`

## Behavior

`ZipPackage::centralDirectoryInventoryPreflight()` now keeps the existing
`central-directory-entry-count-mismatch` issue while also reporting the
direction and magnitude of an EOCD central-directory entry-count mismatch:

- `entryCountDelta`
- `extraScannedEntryCount`
- `missingDeclaredEntryCount`
- `entryCountMismatchKind` as `declared-too-low`, `declared-too-high`, or `null`

This lets strict Office/EPUB/ODT package review distinguish packages with
extra scanned central-directory records from packages whose EOCD claims entries
that were not present in the scanned central directory.

The WordPress ZIP package preflight smoke now emits and self-tests the
under-declared and over-declared EOCD count cases without invoking `zip`,
`unzip`, `ZipArchive`, Pandoc, Word, LibreOffice, or online services.

## Verification

Baseline before this patch:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 2131 assertions, 0 failures`

Red-first after adding only the new focused assertions:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 2111 assertions, 1 failures`
- Failure: missing `entryCountDelta` in the central-directory inventory summary.

Final focused checks:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 2143 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc`

Root harness: not run; isolated micro-slice only.

## Dependency Closure

No new support component is required. This reuses the existing native PHP ZIP
EOCD and central-directory inventory parser and in-memory package fixtures.
The slice does not require shelling out to Pandoc, Cabal/Haskell runners, Word,
LibreOffice, `zip`, `unzip`, `ZipArchive`, external converters, online
services, live provider tests, or live-service provider tests.

## Non-Overlap

This slice does not repeat accepted ZIP package work for stored-first mimetype
data-descriptor rejection, central-directory local-header offset
classification, Unicode format-control filename hygiene, timestamp and extra
field parsing, ZIP64 extra-field metadata, unsupported compression, encryption
preflight, signed/unsigned data descriptors, symlink rejection, or ZIP/OPC
relationship parsing. It only adds count-direction accounting to the existing
central-directory inventory mismatch preflight.

## Next

Suggested next ZIP/package gap: ZIP64 central-directory size/count
reconciliation, bounded central-directory recovery metadata, or a DOCX/EPUB/ODT
reader handoff that consumes the strict ZIP preflight diagnostics.
