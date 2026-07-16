# pandoc-pdf-typst-dash-prefixed-scalar-boundary-20260612T031140Z

Bead: `plib-tiwfk`
Slice: PDF/Typst boundary provenance
Date: 2026-06-12 UTC

This slice preserves dash-prefixed scalar values supplied to Typst numeric
boundary options instead of collapsing them to missing-value provenance.

`PdfEngineHandoff` now allows a constrained `-[0-9]` separated value pattern for:

- `--creation-timestamp`
- `--jobs` / `-j`
- `--pages`
- `--ppi`

The supplied values remain inert review metadata, so negative or otherwise
invalid values are reported as their specific invalid boundary entries. Ordinary
following-option behavior is unchanged because only the numeric dash pattern is
accepted.

The regression covers planning, diagnostics, fake-run artifact provenance, and
sequence propagation without invoking Pandoc, Typst, TeX/PDF engines, browser
renderers, office suites, zip/unzip, Node tooling, external validators, online
services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 2030 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 69797 assertions, 0 failures
