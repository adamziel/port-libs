# PDF/Typst short format alias provenance slice

Bead: `plib-twabo`
Date: 2026-06-12 UTC

This slice extends bounded native `PdfEngineHandoff` Typst output-format
boundary provenance to the short `-f` alias for `--format`.

The short alias now feeds the same `typstOutputFormatPolicy` path as
`--format`, including explicit output format selection, format history,
override diagnostics, fake-run artifact review metadata, and fake-run sequence
propagation. The slice remains metadata-only and does not execute Pandoc,
Typst, TeX/PDF engines, browser renderers, office suites, `zip`/`unzip`, Node
tooling, external validators, online services, live provider tests, or
live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 file, 1889 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 files, 67873 assertions, 0 failures.
