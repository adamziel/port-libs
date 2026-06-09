# Pandoc roff/manual extension classification registry slice 2026-06-09T223001Z

## Summary

Mapped one bounded direct-format registry accounting case for Pandoc
roff/manual extension evidence classification.

- `PandocFormatRegistry::classifyRoffManualExtension()` now returns normalized
  metadata for literal `.ms` and `.roff` extensions, numbered manual sections,
  and manual-section suffixes such as `.3p` and `.5ssl`.
- `PandocFormatRegistry::roffManualExtensionPatternMetadata()` exposes the
  registry pattern inventory and cross-checks it against the existing format
  inference table.
- Roff/manual review packets include the extension pattern metadata while
  preserving unsupported direct native parity for `man`, `mdoc`, and `ms`.

This is registry metadata only. It does not parse or render roff/manual sources
and does not register a native roff/manual reader or writer.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 438 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57557 assertions, 0 failures

No Pandoc executable, roff renderer, Cabal solver/build/test command, Haskell
runner, browser renderer, external validator, online service, live provider
test, or live-service provider test was used.

## Metric Delta

- `lane-status.json` `phpPass`: `2857 -> 2858`
- `lane-status.json` focused checks: `760 -> 761`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3063 -> 3064`
- `mappedPandocFormatRegistryRoffManualExtensionClassificationCases`: `1`
- `pandocFormatRegistryRoffManualExtensionClassificationAssertions`: `24`
