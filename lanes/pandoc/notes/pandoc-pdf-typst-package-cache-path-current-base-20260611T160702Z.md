# pandoc-pdf-typst-package-cache-path-current-base-20260611T160702Z

Slice: `plib-6xm5i`, PDF/Typst boundary provenance.
Required base: `c51492fa8`.

## Change

`PdfEngineHandoff` now recognizes Typst's `--package-cache-path` compile
boundary option as package-cache provenance. The existing `packageCache`
handoff metadata, diagnostics, fake-run artifact review, and fake-run sequence
summary all preserve the normalized cache path without invoking Pandoc, Typst,
TeX/PDF engines, browser renderers, external validators, online services, live
provider tests, or live-service provider tests.

This keeps the prior `--package-cache` behavior intact while covering the
current Typst CLI spelling for package cache paths.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test files, 1673 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 63763 assertions, 0 failures.

