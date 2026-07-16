# pandoc-pdf-typst-overlapping-pages-boundary-20260612T163730Z

Slice: `pandoc-pdf-typst-overlapping-pages-boundary`

`PdfEngineHandoff` now preserves overlapping Typst `--pages` selections as
bounded PDF export boundary provenance. The existing page segment parser still
records the raw selected page/range tokens, while the new `pageSelectionPolicy`
adds review metadata only when parsed segments overlap.

The policy records:
- segment counts for single pages, closed ranges, open-ended ranges, and invalid
  segments;
- finite and open-ended range totals;
- deterministic overlap pairs;
- the `pages-overlapping-selection-boundary` issue, surfaced through plan
  diagnostics, fake-run artifact review, and final fake-run sequence summaries.

This maps one native PHP `PdfEngineHandoffTest` case with 15 focused assertions.
After rebase onto `3fb6dd32ca`, direct-format accounting moves phpPass
3241 -> 3242, phpFail remains 0, and the mapped denominator moves 3261 -> 3262.

Verification on 2026-06-12 UTC:
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 file, 2156 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 72283 assertions, 0 failures

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
