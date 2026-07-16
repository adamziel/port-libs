# ODT settings inventory role slice

Bead: `plib-2boyb`

Base: current `origin/main` `087f5bbbba57abe2b70cbeabaf381cd9c6a6e6cf`

## Scope

- Classifies manifest-declared `settings.xml` in `OpenDocumentPackage` package inventory as `odf-settings`.
- Preserves manifest declaration metadata, stored compression provenance, byte length, byte exposure policy, and undeclared-entry accounting.
- Keeps `settings.xml` outside media handoff while remaining visible to package review queues.
- Native PHP only; no Pandoc, office suite, `zip`/`unzip`, browser, external validator, online service, live provider, or live-service provider calls.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 464 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 67118 assertions, 0 failures`

Lane status: `phpPass` moves `3141 -> 3142`.
