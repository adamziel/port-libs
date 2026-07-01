# Pandoc roff/manual extension-direction summary slice

Micro-slice: `pandoc-roff-manual-extension-direction-summary-20260701`

## Summary

This slice adds aggregate extension-direction summary accounting for the native
PHP Pandoc roff/manual format registry.

- `PandocFormatRegistry::roffManualExtensionDirectionSummary()` summarizes all
  registered roff/manual extension inference patterns by format, direction,
  support status, and extension-pattern kind.
- `PandocFormatRegistry::roffExtensionDirectionSummary()` remains an alias for
  callers using the shorter roff naming.
- `PandocFormatRegistry::roffManualFormatReviewPacket()` now includes the same
  `extensionDirectionSummary` payload for downstream review surfaces.
- Literal `.ms`/`.roff` patterns remain output-only `ms` mappings; numbered
  manual-section patterns remain input-output `man` mappings.
- The summary reports unsupported direct parity for every present roff/manual
  extension surface. No native roff reader or writer implementation was added
  or claimed.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed: 1 file, 377 assertions, 0 failures.

No Pandoc executable, roff renderer, Cabal solver/build/test command, Haskell
runner, TeX/PDF engine, browser renderer, office suite, external validator,
online service, live provider test, live-service provider test, Node tooling,
or zip/unzip command was used for this slice.
