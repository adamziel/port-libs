# PDF/Typst open output viewer provenance

Bead: `plib-78dhp`

This slice extends `PdfEngineHandoff` Typst boundary provenance for `--open`
output-viewer values. The handoff now records default versus explicit viewer
counts, ordered viewer entries, selected explicit viewer metadata, and
program/relative/URI/absolute/invalid classification. Unsafe external viewers
are review-only metadata with `open-output-viewer-external-boundary`; the
handoff still never opens viewers or runs Typst.

The metadata is carried through `plan()`, `fakeRun()`,
`artifactProvenanceReview`, and `fakeRunSequence()`.

Accounting:

- `phpPass`: `3153 -> 3154`
- Added one focused `PdfEngineHandoffTest` PASS case.
- Added `mappedTypstOpenOutputViewerBoundaryCases = 1`.
- Added `typstOpenOutputViewerBoundaryAssertions = 13`.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1890 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 67874 assertions, 0 failures`.

No Pandoc, Cabal/Haskell runners, Typst, TeX/PDF engines, output viewers,
browser renderers, office suites, zip/unzip, Jupyter, Node tooling, external
validators, online services, live provider tests, or live-service provider
tests were run.
