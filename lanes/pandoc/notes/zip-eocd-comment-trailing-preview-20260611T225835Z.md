# ZIP EOCD comment/trailing preview preflight (plib-f72de)

Hook: plib-f72de, Pandoc shared ZIP/OPC package core blocker slice 20260611T225835Z.
Scope: lanes/pandoc only.

## Implementation

- Added bounded EOCD package-comment preview bytes to fixed-field and trailing-byte preflights.
- Added bounded EOCD trailing-byte preview offsets and hex previews for detached archive slack.
- Propagated the new provenance through raw strict import summaries without invoking zip/unzip or external validators.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `git diff --check -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed: 1 test file, 3604 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66872 assertions, 0 failures.

Current main target: e25fac1262.
