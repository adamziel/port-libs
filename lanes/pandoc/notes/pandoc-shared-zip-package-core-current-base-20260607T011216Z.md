# Pandoc ZIP package split archive disk-marker preflight

Slice: `pandoc-shared-zip-package-core-current-base-20260607T011216Z`

Base accepted HEAD: `05080f39db5ee2c2bd812547f2fb1754cdd82f98`

## Behavior

Added native `ZipPackage::splitArchivePreflight()` for bounded package imports. It scans EOCD disk markers and central-directory entry `diskStart` markers, reports split/spanned archive diagnostics, and leaves `ZipPackage::fromString()` fail-closed for unsupported split archives.

This is intentionally diagnostic/planning support only. It does not implement split archive extraction and does not shell out to `zip`, `unzip`, `ZipArchive`, Pandoc, Word, LibreOffice, or online services.

## Evidence

Baseline focused test:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 980 assertions, 0 failures`.

Final focused test:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 1012 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`

Result: `zip package writer preflight self-test passed`.

Syntax checks:

`php -l lanes/pandoc/src/ZipPackage.php`

`php -l lanes/pandoc/tests/ZipPackageTest.php`

`php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`

Result: all reported no syntax errors.

## Status delta

Mapped one additional ZIP package support case.

Focused PHP PASS delta: `+1`.

Focused assertion delta: `+32`.

`lanes/pandoc/lane-status.json` `phpPass`: `1425 -> 1426`.

`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` ZIP package support cases: `22 -> 23`; ZIP package core assertions: `161 -> 193`.

## Dependency closure

No new support component is needed. This reuses the existing native PHP `ZipPackage` EOCD and central-directory parsing plus the existing WordPress ZIP package preflight example and lane-local focused test harness.

Full Pandoc runner parity remains outside this micro-slice and requires the pinned upstream checkout plus explicit Haskell/Cabal runner authorization.

## Non-overlap

This slice avoids the accepted ZIP package support clusters for central-directory signatures, Unicode path/comment extra fields, strict central-directory signatures, invalid DOS timestamps, general-purpose flag policy, ZIP64 extra-field preflight, and malformed Info-ZIP extended timestamps. It only adds split/spanned archive disk-marker diagnostics around the already fail-closed split archive reader behavior.
