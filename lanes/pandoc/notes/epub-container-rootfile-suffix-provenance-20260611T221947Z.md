# EPUB Container Rootfile Suffix Provenance

Slice: `pandoc-epub-container-rootfile-suffix-provenance-20260611T221947Z`

Current base: `origin/main` at `4172e4cba`.

## Summary

`EpubPackage` now normalizes OCF `container.xml` rootfile `full-path`
values with query and fragment suffixes to the stripped OPF package part for
package reads while preserving the original target suffix for review handoff.

The rootfile validation report now exposes `target`, `fullPathQuery`,
`fullPathFragment`, `fullPathSuffixItems`, and query/fragment diagnostics, and
the same package-validation structure is available to WordPress import review
metadata.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
  - no syntax errors.
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
  - no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1565 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66628 assertions, 0 failures.

No Pandoc executable, EPUBCheck, zip/unzip, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.
