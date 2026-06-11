# EPUB3 OPF link media-type parameter slice

Date: 2026-06-11 UTC
Base: current main d84ad700ea
Bead: plib-w1z9n

## Scope

`EpubPackage` now preserves optional `media-type` parameter provenance on EPUB link records:

- OPF metadata/package links
- OPF collection links
- OCF container metadata links, via the same bounded helper

Each link records raw media type, normalized media type, base media type, parameter list/map/count, syntax validity, and link-specific media-type diagnostics. The focused fixture covers quoted parameters, semicolon-containing quoted values, WordPress review summary handoff, and malformed link media-type parameter diagnostics.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1329 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64963 assertions, 0 failures

No Pandoc executable, EPUBCheck, zip/unzip tooling, browser renderer, external validator, online service, or live provider test was invoked.
