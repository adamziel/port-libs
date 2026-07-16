# Pandoc PDF Typst Excessive PPI Boundary

Slice: `plib-m0tz9`, PDF/Typst boundary provenance.

This slice extends native `PdfEngineHandoff` coverage for Typst `--ppi` PDF
export controls by locking the excessive selected-value path. The handoff
already parses `--ppi` without invoking Typst; the added fixture verifies that a
selected value above the bounded native cap is preserved as inert review
metadata with `ppi-excessive-boundary`, `safe: false`, and a null normalized PPI.

The focused test checks plan diagnostics, `typstBoundaryProvenance`,
fake-run `artifactProvenanceReview`, and `fakeRunSequence()` final provenance.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2177 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 72894 assertions, 0 failures`

Metric/accounting:

- `phpPass`: `3260 -> 3261`
- `phpFail`: `0`
- `mappedTypstPpiExcessiveBoundaryCases`: `1`
- `typstPpiExcessiveBoundaryAssertions`: `12`

This does not run Pandoc, Cabal/Haskell runners, Typst, TeX/PDF engines,
browser renderers, external PDF validators, office suites, zip/unzip, online
services, live provider tests, or live-service provider tests. It is limited to
bounded native PHP provenance at the PDF/Typst handoff boundary.
