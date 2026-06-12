# PDF/Typst Font Path List Boundary Provenance

Bead: `plib-608u1`
Base: `daca348385`
Date: 2026-06-12 UTC

This slice keeps native `PdfEngineHandoff` Typst compile boundary provenance
aligned with Typst font path list handling. `--font-path` values are now split
on the platform path separator before boundary classification, so each declared
font directory keeps its own relative, absolute, URI, invalid, safe, and issue
metadata instead of being collapsed into one opaque path value.

The focused fixture covers mixed relative and external entries in repeated
`--font-path` options, and verifies planner diagnostics, fake-run artifact
review propagation, and fake-run sequence summaries. It does not execute
Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 2118 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 71931 assertions, 0 failures

Accounting:

- `phpPass`: 3231 -> 3232
- mapped denominator: 3251 -> 3252
- `mappedTypstFontPathListBoundaryCases`: 1
- `typstFontPathListBoundaryAssertions`: 10
