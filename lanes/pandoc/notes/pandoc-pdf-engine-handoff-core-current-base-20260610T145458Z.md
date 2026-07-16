# pandoc-pdf-engine-handoff-core-current-base-20260610T145458Z

Slice: `plib-1x6l`, PDF/Typst boundary provenance.

This slice tightens native `PdfEngineHandoff` Typst dependency sidecar
provenance. Typst make-style depfiles may include package references such as
`@preview/cetz:0.3.2`; those are package inputs, not workspace files. The fake
runner now classifies that reference shape as external package provenance under
`typst-package:*`, keeps it out of `missingEngineInputFiles`, exposes
`engineTypstPackageInputs`, carries `finalEngineTypstPackageInputs` through
fake-run sequences, and emits `engine-typst-package-inputs:*` diagnostics.

Focused coverage extends the existing Typst depfile fake-run test with a
package reference beside local Typst/image inputs and an absolute font path. It
proves package inputs do not require local file presence while still preserving
the package token for review.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1475 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60432 assertions, 0 failures

This does not run Pandoc, Typst, TeX/PDF engines, browser engines, external PDF
validators, unzip/zip, Node tooling, office suites, Jupyter, or network-backed
resource fetching. It is limited to bounded native PHP planning/fake-runner
provenance at the PDF/Typst handoff boundary.
