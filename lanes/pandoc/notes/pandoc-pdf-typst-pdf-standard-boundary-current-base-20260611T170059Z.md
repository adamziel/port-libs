# pandoc-pdf-typst-pdf-standard-boundary-current-base-20260611T170059Z

Slice: `plib-74vn4`, PDF/Typst boundary provenance.
Required base: `bd4af01a0`.

## Change

`PdfEngineHandoff` now records Typst `--pdf-standard` boundary provenance.
Each supplied standard keeps its raw token, normalized value, PDF family
(`pdf/a`, `pdf/ua`, `pdf/x`, or `unknown`), conformance suffix, safe flag, and
review issues for empty, invalid, or unknown values.

The provenance is carried through plans, fake-run artifact review metadata, and
fake-run sequence summaries. Plan diagnostics now include the supplied PDF
standard count and aggregate boundary issue count.

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test files, 1685 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 64159 assertions, 0 failures.
