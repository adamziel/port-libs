# Pandoc EPUB Nav Entry Label Diagnostics Slice

## Scope

- Added compact native `EpubPackage` diagnostics for non-primary EPUB nav
  document entries that have a target but no reviewable text label, while
  preserving the existing primary nav item-label diagnostics.
- The diagnostic is reported through package validation and the existing
  `summary()['wordpressImport']['navDocumentDiagnostics']` handoff path.
- Kept the slice under `lanes/pandoc` and did not invoke Pandoc, EPUBCheck,
  browser renderers, external validators, online services, or archive tools.

## Verification

- Baseline focused run before patch:
  `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 637 assertions, 0 failures
- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- Focused run after patch:
  `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 680 assertions, 0 failures
- Full post-rebase run after the current-base rich package extension-inference registry slice:
  `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58063 assertions, 0 failures

## Accounting

- `phpPass` moves from 2883 to 2884 with one focused EPUB nav diagnostics pass
  case.
- `phpFail` remains 0.
- The mapped denominator moves from 3082 to 3083; mapped suite progress moves
  from 786 to 787 focused checks.
