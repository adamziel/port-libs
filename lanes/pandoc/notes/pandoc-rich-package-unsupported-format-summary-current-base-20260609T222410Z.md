# Pandoc rich package unsupported-format summary slice 2026-06-09T222410Z

## Summary

Mapped one bounded direct-format registry accounting case for Pandoc rich
package unsupported-format surfaces.

- `PandocFormatRegistry::richPackageUnsupportedFormatSummary()` now separates
  unsupported-both, partial-input/unsupported-output, input-only unsupported,
  output-only unsupported, and no-native-reader/writer package tokens.
- `PandocFormatRegistry::richPackageFormatReviewPacket()` includes that summary
  so review handoffs can audit unsupported rich package surfaces without
  re-deriving them from per-format entries.
- Existing partial native input accounting for DOCX, EPUB, and ODT remains
  unchanged.
- No native writer parity is claimed for DOCX, EPUB, ODT, notebooks,
  presentations, PDFs, chunked HTML, ICML, or OpenDocument package outputs.

No Pandoc executable, office suite, notebook tooling, TeX/PDF engine, browser
renderer, zip/unzip command, Cabal solver/build/test command, Haskell runner,
external validator, online service, live provider test, or live-service
provider test was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 506 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57643 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `2858 -> 2859`
- `lane-status.json` focused checks: `761 -> 762`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3064 -> 3065`
- `mappedPandocRichPackageUnsupportedFormatCases`: `1`
- `pandocRichPackageUnsupportedFormatAssertions`: `68`
