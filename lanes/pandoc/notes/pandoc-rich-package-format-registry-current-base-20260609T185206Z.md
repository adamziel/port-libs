# Pandoc rich package format registry slice 2026-06-09T185206Z

## Summary

Mapped one bounded direct-format registry accounting case for Pandoc rich package
formats.

- Input registry family: `docx`, `epub`, `ipynb`, `odt`, `pptx`, `xlsx`.
- Output registry family: `chunkedhtml`, `docx`, `epub`, `epub2`, `epub3`,
  `icml`, `ipynb`, `odt`, `opendocument`, `pdf`, `pptx`.
- Unsupported input tokens remain explicit for `ipynb`, `pptx`, and `xlsx`.
- Unsupported output tokens remain explicit for every package output token until
  a native PHP writer path exists.

Existing partial native input accounting for DOCX, EPUB, and ODT remains
unchanged. This slice does not claim direct native writer parity for DOCX, EPUB,
ODT, notebooks, presentations, PDFs, or chunked HTML packages.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, TeX/PDF engine, browser renderer, notebook tooling, zip/unzip
command, external validator, online service, live provider test, or
live-service provider test was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 213 assertions, 0 failures
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 56941 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `2821 -> 2822`
- `lane-status.json` focused checks: `724 -> 725`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3041 -> 3042`
- `mappedPandocRichPackageFormatRegistryCases`: `1`
- `pandocRichPackageFormatRegistryAssertions`: `56`
