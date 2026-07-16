# pandoc-pdf-typst-dependency-edge-provenance-current-base-20260610T182728Z

Slice: `plib-8mf1`, PDF/Typst boundary provenance.

This slice extends native `PdfEngineHandoff` Typst dependency sidecar
provenance from aggregate file sets into bounded row-level make-dependency edge
metadata. Fake runs now expose `engineDependencyEdges`, carry it into
`artifactProvenanceReview`, and preserve the final edge list through
`fakeRunSequence()`.

Each edge records the dependency artifact path, declared output targets, local
input files, and external inputs such as Typst package references. Existing
aggregate fields (`engineInputFiles`, `engineExternalInputFiles`,
`engineOutputFiles`, package dependencies, and output-target policy) remain
unchanged.

Focused coverage adds a Typst make depfile with two rules for the same planned
PDF target. It verifies edge metadata, artifact-provenance handoff, diagnostics,
and sequence carry-forward without invoking Typst or any renderer.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1501 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 60970 assertions, 0 failures`

This does not run Pandoc, Cabal/Haskell runners, Typst, TeX/PDF engines,
browser renderers, external PDF validators, office suites, zip/unzip, online
services, live provider tests, or live-service provider tests. It is limited to
bounded native PHP fake-runner provenance at the PDF/Typst handoff boundary.
