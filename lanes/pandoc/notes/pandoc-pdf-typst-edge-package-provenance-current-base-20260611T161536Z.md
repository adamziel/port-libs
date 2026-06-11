# pandoc-pdf-typst-edge-package-provenance-current-base-20260611T161536Z

Slice: `plib-zejkp`, PDF/Typst boundary provenance.

This slice extends native `PdfEngineHandoff` fake-run provenance so Typst
make-dependency edge rows that introduce package references also carry
structured package metadata at the edge level. The existing aggregate
`engineTypstPackageDependencies` remains intact; the new
`typstDependencyEdgePackageProvenance` review field records the dependency
artifact, output targets, local co-inputs, package input tokens, and parsed
namespace/package/version/subpath records for each relevant make rule.

Focused coverage adds one bounded fake-runner case with two Typst package
dependency edges and verifies the result, artifact provenance review packet,
diagnostic count, and multipass sequence summary.

Verification so far:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1681 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 63890 assertions, 0 failures`

This does not run Pandoc, Cabal/Haskell runners, Typst, TeX/PDF engines,
browser renderers, external PDF validators, office suites, zip/unzip, online
services, live provider tests, or live-service provider tests. It is limited to
bounded native PHP fake-runner provenance at the PDF/Typst handoff boundary.
