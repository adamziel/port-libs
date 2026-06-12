# ZIP Comment Byte Provenance

Bead: plib-834ed
Base: current main 0339a50490
Date: 2026-06-12 UTC

## Scope

- Added EOCD package-comment byte provenance directly to ZIP comment policy packets: offset, end offset, raw preview hex, and preview byte count.
- Added central-directory entry raw-comment byte provenance to comment summaries: offset, end offset, raw preview hex, and preview byte count.
- Verified propagation through object comment preflight, strict import, raw comment policy, and raw strict import review paths.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed: 1 test file, 3649 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 67901 assertions, 0 failures.

No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
