# pandoc-pdf-typst-timings-stdout-boundary-current-base-20260612T012625Z

Slice: `plib-uyzmh`, PDF/Typst boundary provenance.
Base: `65bad4e34f97ad91abc77ff4ad8c890ad52effbe`

`PdfEngineHandoff` now treats Typst `--timings=-` as stdout boundary
provenance instead of a relative timing sidecar path. The plan keeps stdout out
of `expectedEngineArtifacts`, records `timings-output-stdout-boundary`, and
carries the review metadata through fake-run artifact review and multipass
sequence summaries.

This remains native PHP handoff behavior and does not invoke Pandoc, Typst,
TeX/PDF engines, browser renderers, external validators, online services, live
provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test file, 1964 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 68837 assertions, 0 failures`