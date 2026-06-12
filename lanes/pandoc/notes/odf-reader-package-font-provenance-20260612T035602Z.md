# ODF reader package font provenance slice

Bead: `plib-bhuvk`

Base: current `origin/main` `acb8fb36b3ee643643f60c3496909625d2b4dbc2`

## Scope

- Added `OdfReader::readPackage()` parity for ODF/ODT package font parts.
- Reports manifest-declared, undeclared, missing, encrypted, invalid-media-type, and media-type-parameter font package evidence through `packageFonts`.
- Mirrors the font summary into document attrs, top-level metadata as `odfPackageFonts`, and `importReport['packageFonts']`.
- Marks font package parts as `font-package` in package provenance and blocks font payload byte exposure from document media handoff with `font-package-bytes-blocked`.
- Native PHP only; no Pandoc, office suite, `zip`/`unzip`, browser, external validator, online service, live provider, or live-service provider calls.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test file, 4237 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 70500 assertions, 0 failures`

Lane status: `phpPass` moves `3188 -> 3189`.
