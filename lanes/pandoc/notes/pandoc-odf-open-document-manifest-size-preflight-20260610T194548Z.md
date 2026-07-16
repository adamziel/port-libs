# ODF/OpenDocument Manifest Size Preflight

Bead: `plib-xcbco`

## Behavior

- `OpenDocumentPackage` now validates present `manifest:size` attributes before
  exposing strict ODT package manifest entries.
- Empty or absent sizes remain `null`.
- Decimal digit sizes, including leading-zero values, are accepted and
  normalized to integers.
- Malformed, signed, decimal, and platform-overflowing sizes now raise
  `InvalidArgumentException` during package construction.

## Focused Coverage

- Added `rejects malformed ODT manifest size metadata before package exposure`
  to `lanes/pandoc/tests/OpenDocumentPackageTest.php`.
- The fixture covers a valid leading-zero size plus malformed suffix, negative,
  signed, decimal, and overflowing size values.

## Accounting

- This is one bounded native ODF/ODT package-ingestion case.
- It does not invoke Pandoc, office suites, `zip`/`unzip`, browser renderers,
  Cabal/Haskell runners, external validators, online services, or live provider
  tests.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 99 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 61087 assertions, 0 failures
