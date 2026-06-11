# EPUB3 Rootfile Suffix Provenance Slice 20260611T165721Z

- Bead: `plib-45sv9`
- Base: `origin/main` `6995e705a`
- Scope: bounded EPUB3 package ingestion.
- Change: OCF `META-INF/container.xml` rootfile `full-path` values now preserve query and fragment suffix provenance while package selection uses the stripped OPF part path.
- Review handoff: rootfile records expose `fullPathHasQuery`, `fullPathQuery`, `fullPathHasFragment`, and `fullPathFragment`; package validation exposes suffix diagnostics and `fullPathSuffixItems` through the existing WordPress review metadata.
- Verification:
  - `php -l lanes/pandoc/src/EpubPackage.php`
  - `php -l lanes/pandoc/tests/EpubPackageTest.php`
  - `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` -> 1 test file, 1179 assertions, 0 failures
  - `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 64107 assertions, 0 failures

No Pandoc, EPUBCheck, office suite, zip/unzip, browser renderer, external validator, online service, or live provider was invoked.
