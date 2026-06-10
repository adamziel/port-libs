# Pandoc roff/manual unsupported extension surface registry slice

Micro-slice: `pandoc-roff-manual-unsupported-extension-surfaces-current-base-20260610T104827Z`

## Summary

This slice adds per-extension unsupported surface metadata for the native PHP
Pandoc roff/manual format registry.

- `roffManualUnsupportedFormatForExtension()` classifies `.ms`, `.roff`,
  numeric manual-section suffixes, and suffixed manual-section extensions into
  the existing roff/manual direction and support-status model.
- `roffManualUnsupportedExtensionSurfaces()` exposes the registered extension
  inference patterns as review records, including unsupported input/output
  booleans and empty implementation classes.
- `roffManualFormatReviewPacket()` now carries those extension surface records,
  and `roffManualFormatParitySummary()` counts the unsupported extension-surface
  mappings.

No native `man`, `mdoc`, or `ms` reader or writer was registered.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed: 1 file, 1017 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed after rebase: 44 files, 59472 assertions, 0 failures.

`lane-status.json` `phpPass`: `2951 -> 2952`; `phpFail` remains `0`.

No Pandoc executable, roff renderer, Cabal solver/build/test command, Haskell
runner, browser renderer, office suite, external validator, online service,
Node tooling, or zip/unzip command was used for this slice.
