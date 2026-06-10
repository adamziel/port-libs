# pandoc-pdf-typst-package-dependency-provenance-current-base-20260610T164316Z

Slice: `plib-pud3`, PDF/Typst boundary provenance.

This slice extends native `PdfEngineHandoff` Typst dependency sidecar
provenance from opaque `typst-package:*` inputs into structured reviewer
metadata. Typst make-style depfiles can reference packages such as
`@preview/cetz:0.3.2/src/lib.typ`; fake runs now preserve the package reference
and expose namespace, package name, version, and optional subpath in
`engineTypstPackageDependencies`, `artifactProvenanceReview`, and
fake-run sequence final output.

Focused coverage adds a bounded fake-run case for a Typst `--deps` sidecar with
two package references and an absolute font dependency. It verifies package
input classification, structured dependency metadata, provenance-review
handoff, diagnostics, and sequence carry-forward without invoking Typst.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1482 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60759 assertions, 0 failures

This does not run Pandoc, Cabal/Haskell runners, Typst/PDF engines, browser
renderers, external PDF validators, office suites, zip/unzip, online services,
live provider tests, or live-service provider tests. It is limited to bounded
native PHP fake-runner provenance at the PDF/Typst handoff boundary.
