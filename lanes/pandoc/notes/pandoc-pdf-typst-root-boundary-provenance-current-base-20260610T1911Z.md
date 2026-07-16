# pandoc-pdf-typst-root-boundary-provenance-current-base-20260610T1911Z

Slice: `plib-h9fxs`, PDF/Typst boundary provenance.

This slice extends native `PdfEngineHandoff` Typst fake-run provenance with a
bounded read-boundary review policy for declared `--root` engine options. Plans
now preserve safe workspace-relative Typst root provenance, and fake runs compare
the source plus dependency-sidecar local inputs against that root without
invoking Typst or any renderer.

The new `typstReadBoundaryPolicy` reports the declared root, reviewed input
files, inside-root files, outside-root files, review issues, and is carried into
`artifactProvenanceReview` and `fakeRunSequence()` final state. On current main,
out-of-root dependency reads are also carried through the shared
`engineBoundaryViolations` surface and make the fake run fail with
`engine-boundary-violation`; the read policy remains available as structured
review provenance for the same boundary.

Focused coverage adds a Typst depfile with one source-rooted input and one
shared out-of-root input. It verifies plan diagnostics, fake-run diagnostics,
artifact provenance, and sequence carry-forward.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1600 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 61827 assertions, 0 failures`

Accounting:

- `phpPass`: 3032 -> 3033 after rebase onto `origin/main` de151e22.
- mapped denominator: 3171 -> 3172 after rebase onto `origin/main` de151e22.
- Added `mappedTypstRootReadBoundaryProvenanceCases = 1`.
- Added `typstRootReadBoundaryProvenanceAssertions = 21`.

This does not run Pandoc, Cabal/Haskell runners, Typst, TeX/PDF engines,
browser renderers, external PDF validators, office suites, zip/unzip, Jupyter,
Node tooling, online services, live provider tests, or live-service provider
tests. It is limited to bounded native PHP planning and fake-runner provenance
at the PDF/Typst handoff boundary.
