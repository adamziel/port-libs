# pandoc-pdf-typst-timings-boundary-current-base-20260611T181739Z

Slice: `plib-42e0e`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now preserves Typst `--timings` sidecar provenance. Safe
relative timing JSON paths are planned as expected engine artifacts; external,
empty, invalid, or overridden timing paths remain review-only boundary metadata.

The provenance is carried through plan diagnostics, fake-run artifact review,
and multipass sequence summaries without invoking Pandoc, Typst, TeX/PDF
engines, browser renderers, external validators, online services, live provider
tests, or live-service provider tests.

Verification:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
