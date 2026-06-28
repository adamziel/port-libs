# Pandoc PDF Viewer Nonfullscreen Page Mode Policy

Slice: `plib-utlj6`, PDF/Typst boundary provenance.

This slice extends native PHP `PdfEngineHandoff` produced-PDF review metadata for
catalog viewer preferences. The fake runner already extracted catalog
`/PageMode` and `/ViewerPreferences`; it now also passes the catalog page mode
into `pdfViewerPreferencePolicy` so `/NonFullScreenPageMode` can be reviewed in
context.

The new bounded policy fields are:

- `pageMode`;
- `fullScreenRequested`;
- `nonFullScreenPageMode`;
- `nonFullScreenPageModePresent`.

When a produced PDF declares `/ViewerPreferences /NonFullScreenPageMode` while
catalog `/PageMode` is not `/FullScreen`, the policy records
`non-fullscreen-page-mode-without-fullscreen` and exposes the issue through
fake-run diagnostics, artifact sequence carry-forward, and the focused test.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 3596 assertions, 0 failures`

This does not run Pandoc, Typst, TeX/PDF engines, browser renderers, office
suites, external PDF validators, network services, zip/unzip, or upstream
Haskell/Cabal runners. It is limited to bounded native PHP fake-produced PDF
provenance at the PDF/Typst handoff boundary.
