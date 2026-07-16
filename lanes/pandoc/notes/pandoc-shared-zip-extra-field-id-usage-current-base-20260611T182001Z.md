# Shared ZIP Extra Field ID Usage Summary

Date: 2026-06-11
Bead: plib-2po8w
Base accepted HEAD: `ecdae07bd`

This slice extends the shared ZIP/OPC package preflight so DOCX, EPUB, and
ODF-style readers can review ZIP extra-field ID usage before package payloads
are exposed.

Implementation:
- Added aggregate central/local/shared/central-only/local-only extra-field ID
  counts to `ZipPackage::extraFieldPreflight()` and
  `ZipPackage::extraFieldPolicyPreflight()`.
- Added deterministic `extraFieldIdUsage` rows with per-ID record counts,
  entry counts, header presence flags, and central/local entry-name
  provenance.
- Kept duplicate, ID-mismatch, and value-mismatch policy diagnostics intact
  while exposing the same usage summary through raw strict preflight.

Verification:
- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed:
  1 test file, 3183 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed:
  44 test files, 65030 assertions, 0 failures.

Accounting:
- Adds 1 mapped shared ZIP extra-field ID usage case.
- Adds 28 focused assertions.
- Moves Pandoc lane `phpPass` from 3091 to 3092 on current main `ecdae07bd`.
- Keeps `phpFail` at 0.

No Pandoc, office suite, `zip`/`unzip`, browser renderer, external validator,
online service, live provider test, or live-service provider test was invoked.
