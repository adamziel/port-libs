# Pandoc Roff/Manual Extension Directions Current Base

Slice: `pandoc-roff-extension-directions-current-base-20260610T0000Z`

## Summary

This slice extends the native PHP Pandoc format registry accounting for the
roff/manual family without adding or claiming a native roff reader/writer:

- `PandocFormatRegistry::roffManualExtensionDirections()` reports direction
  and support-status buckets for every registered roff/manual extension
  inference pattern.
- `.ms` and `.roff` are derived as output-only `ms` entries.
- `.[1-9]` numbered manual suffixes and `.[1-9][a-z]+` suffixed manual sections
  are derived as input-output `man` entries.
- `mdoc` remains input-only in the format registry and has no extension
  inference claim.
- All present roff/manual directions still report `unsupported` direct parity
  status with no PHP implementation class; absent directions report
  `not-applicable`.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - Result: 1 test file, 944 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 43 test files, 59070 assertions, 0 failures.

Status delta after rebase:

- `lane-status.json` `phpPass`: `2941 -> 2942`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3116 -> 3117`.
- Added `mappedPandocFormatRegistryRoffManualExtensionDirectionCases: 1`.
- Added `pandocFormatRegistryRoffManualExtensionDirectionAssertions: 26`.

No Pandoc executable, roff renderer, Cabal solver/build/test command, Haskell
runner, TeX/PDF engine, browser renderer, office suite, external validator,
online service, live provider test, live-service provider test, Node tooling,
or zip/unzip command was used for this slice.
