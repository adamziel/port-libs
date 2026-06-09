# Pandoc rich package format direction slice 2026-06-09T2037Z

## Summary

Mapped one bounded direct-format registry accounting case for Pandoc rich
package/rich-document direction buckets.

- Bidirectional package tokens: `docx`, `epub`, `ipynb`, `odt`, `pptx`.
- Input-only package token: `xlsx`.
- Output-only package tokens: `chunkedhtml`, `epub2`, `epub3`, `icml`,
  `opendocument`, `pdf`.
- Partial native input status remains limited to the existing DOCX, EPUB, and
  ODT readers.
- Unsupported rich package inputs and all rich package outputs remain explicit;
  no direct native writer parity is claimed.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, notebook tooling, TeX/PDF engine, browser renderer, zip/unzip
command, external validator, online service, live provider test, or
live-service provider test was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 338 assertions, 0 failures
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57294 assertions, 0 failures

## Metric Delta

- Rebased on `bf56c62fe`.
- `lane-status.json` `phpPass`: `2840 -> 2841`
- `lane-status.json` focused checks: `743 -> 744`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3055 -> 3056`
- `mappedPandocRichPackageFormatDirectionCases`: `1`
- `pandocRichPackageFormatDirectionAssertions`: `64`
