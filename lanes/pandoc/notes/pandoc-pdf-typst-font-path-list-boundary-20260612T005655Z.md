# PDF/Typst Font Path List Boundary Provenance

Bead: `plib-608u1`
Base: `dc3e3aa241`
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
  - 1 test file, 1908 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 68164 assertions, 0 failures

Accounting:

- `phpPass`: 3158 -> 3159
- mapped denominator: 3225 -> 3226
- `mappedTypstFontPathListBoundaryCases`: 1
- `typstFontPathListBoundaryAssertions`: 10
