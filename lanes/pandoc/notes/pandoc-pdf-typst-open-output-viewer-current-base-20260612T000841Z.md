# pandoc-pdf-typst-open-output-viewer-current-base-20260612T000841Z

Slice: `plib-78dhp`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now preserves Typst `--open` output-viewer value
provenance as inert review metadata. This extends the prior bare-flag slice by
recording default versus explicit viewer counts, the selected explicit viewer,
ordered viewer entries, relative/program/URI classification, and unsafe viewer
diagnostics such as `open-output-viewer-external-boundary`.

The metadata is carried through `plan()`, `fakeRun()`,
`artifactProvenanceReview`, and `fakeRunSequence()` without invoking Typst or
opening any output viewer.

Accounting:

- `phpPass`: `3149 -> 3150`.
- Added one focused `PdfEngineHandoffTest` PASS case.
- Added `mappedTypstOpenOutputViewerBoundaryCases = 1`.
- Added `typstOpenOutputViewerBoundaryAssertions = 13`.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1878 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 67593 assertions, 0 failures`.

No Pandoc, Cabal/Haskell runners, Typst, TeX/PDF engines, output viewers,
browser renderers, office suites, zip/unzip, Jupyter, Node tooling, external
validators, online services, live provider tests, or live-service provider
tests were run.
