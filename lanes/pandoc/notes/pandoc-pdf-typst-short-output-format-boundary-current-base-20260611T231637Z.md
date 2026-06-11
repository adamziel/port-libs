# pandoc-pdf-typst-short-output-format-boundary-current-base-20260611T231637Z

Slice: `plib-j1uu6`

## Summary

`PdfEngineHandoff` now treats Typst short output-format options `-f` and
`-f=...` as aliases for `--format` in PDF boundary policy. The native handoff
keeps conflicting explicit format requests visible, records the selected
non-PDF format, and carries the review policy through plan diagnostics,
fake-run artifact review, and sequence summaries.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed 1 file, 1858 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files, 67050
  assertions, 0 failures.

No Pandoc, Typst, TeX/PDF engine, browser renderer, external validator, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted Typst root/font/package/cert/input/timestamp,
page/PPI/PDF-standard/tags, diagnostics, timings, dependency sidecar, feature,
jobs, open-output, or long `--format` boundary work. It owns only the short
`-f`/`-f=...` output-format alias at the PDF handoff boundary.
