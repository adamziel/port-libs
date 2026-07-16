# pandoc-pdf-typst-short-timings-boundary-current-base-20260612T013951Z

Slice: `plib-4v88s`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now preserves Typst short `-t` timings sidecar provenance
alongside `--timings`. Both attached `-tpath` and split `-t path` values flow
through expected engine artifact planning, boundary provenance, override
diagnostics, fake-run artifact review, and multipass sequence summaries.

The regression remains native PHP-only and does not invoke Pandoc, Typst,
TeX/PDF engines, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
