# Pandoc roff/manual unsupported-format summary slice 2026-06-09T224225Z

## Summary

Mapped one bounded direct-format registry accounting case for Pandoc
roff/manual unsupported reader/writer surfaces.

- `PandocFormatRegistry::roffManualUnsupportedFormatSummary()` now separates
  `man` as unsupported-both, `mdoc` as unsupported input-only, and `ms` as
  unsupported output-only.
- `PandocFormatRegistry::roffManualFormatReviewPacket()` includes the summary
  beside existing direction buckets, extension inference, pattern metadata, and
  unsupported input/output lists.
- `unsupportedRoffManualInputFormats()` and
  `unsupportedRoffManualOutputFormats()` expose no-native reader/writer lists
  for the roff/manual family.

Direct native parity remains conservative: no PHP `man`, `mdoc`, or `ms`
reader/writer implementation is registered. This is registry metadata only; it
does not parse or render roff/manual sources.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 534 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57714 assertions, 0 failures
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

No Pandoc executable, roff renderer, Cabal solver/build/test command, Haskell
runner, browser renderer, external validator, online service, live provider
test, or live-service provider test was used.

## Metric Delta

- `lane-status.json` `phpPass`: `2865 -> 2866`
- `lane-status.json` focused checks: `768 -> 769`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3070 -> 3071`
- `mappedPandocFormatRegistryRoffManualUnsupportedFormatCases`: `1`
- `pandocFormatRegistryRoffManualUnsupportedFormatAssertions`: `28`
