# ZIP Local Header Variable Field Provenance

Bead: `plib-zwhyf`
Base: `0ba6b0e01`

This slice keeps shared ZIP package preflight accounting visible before DOCX,
EPUB, or ODF package readers trust package entries.

- `ZipPackage::localHeaderPreflight()` now reports aggregate local name bytes,
  local extra-field bytes/counts, and data-descriptor byte counts.
- `ZipPackage::localHeaderSpanPreflight()` reports the same aggregate fields
  from raw package bytes before instantiation.
- The focused test verifies propagation through raw strict import and
  instantiated strict import summaries.

Verification on 2026-06-11 UTC:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 3203 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 65658 assertions, 0 failures

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.
