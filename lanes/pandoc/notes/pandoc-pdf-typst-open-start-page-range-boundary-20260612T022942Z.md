# pandoc-pdf-typst-open-start-page-range-boundary-20260612T022942Z

Slice: `plib-dlisp`, PDF/Typst boundary provenance.

This slice extends native PHP `PdfEngineHandoff` Typst PDF export boundary
provenance for open-start `--pages` ranges such as `-2`. The page-selection
summary now records those segments as `range-to` with a null start and bounded
end page, alongside existing single-page, closed-range, and open-end range
forms.

The focused test keeps the handoff non-executing: the fake runner receives
prebuilt PDF-like bytes and verifies that the plan, fake-run artifact review,
and sequence summary all preserve the same `typstBoundaryProvenance` packet.

Verification on current `origin/main` `412827d77a80`:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1998 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69466 assertions, 0 failures`

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
