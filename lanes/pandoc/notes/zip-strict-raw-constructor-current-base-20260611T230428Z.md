# Shared ZIP/OPC strict raw constructor slice

Bead: `plib-caahw`
Base verified: `d504ad4468`

## Scope

`ZipPackage` now exposes an opt-in strict raw-byte construction path:

- `ZipPackage::assertRawStrictImportable()` returns the existing raw strict preflight summary when importable and throws a diagnostic-rich `RuntimeException` otherwise.
- `ZipPackage::fromStrictString()` requires that raw strict preflight to pass before returning a package object.

The existing `fromString()` behavior remains available for callers that intentionally need non-strict package instantiation.

## Coverage

Added `constructs zip packages only after raw strict import preflight passes` in `ZipPackageTest`.

The focused case covers:

- successful strict construction for a normal ZIP package;
- rejection of package or entry comments before strict construction;
- rejection of local-header spoofing before package exposure, preserving both raw strict diagnostics and constructor failure diagnostics.

This slice does not change comment byte total accounting, local-header spoof parsers, selected-entry handoff, central-directory repair, data descriptors, ZIP64 policy, or generated ZIP writing.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> 1 file, 3622 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 files, 67213 assertions, 0 failures

No Pandoc, office suite, `zip`, `unzip`, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
