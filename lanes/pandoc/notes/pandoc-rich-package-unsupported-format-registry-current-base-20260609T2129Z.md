# Pandoc rich package unsupported-format registry slice 2026-06-09T2129Z

## Summary

Mapped one bounded direct-format registry case for Pandoc rich package
unsupported-format accounting.

- Current `main` already had richer extension inference and extension metadata
  for package tokens including `.docx`, `.epub`, `.fodt`, `.icml`, `.ipynb`,
  `.odt`, `.pdf`, `.pptx`, and `.xlsx`.
- This slice adds an extension-level unsupported-format report that separates
  input and output direction status for a known package extension.
- Keeps existing DOCX, EPUB, and ODT native readers as `partial` input support.
- Keeps notebook, presentation, spreadsheet, PDF, ICML, flat OpenDocument, and
  package writer paths explicit as unsupported direct parity where no native PHP
  reader or writer is registered.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, notebook tooling, TeX/PDF engine, browser renderer, zip/unzip
command, external validator, online service, live provider test, or
live-service provider test was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 756 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58420 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `2906 -> 2907`
- `lane-status.json` focused checks: `809 -> 810`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3095 -> 3096`
- `mappedPandocRichPackageUnsupportedFormatCases`: `1 -> 2`
- `pandocRichPackageUnsupportedFormatAssertions`: `68 -> 89`
