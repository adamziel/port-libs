# PDF page language provenance slice

Bead: `plib-4xsef`
Base: `8db1f530b7d051cb0b5821b11f7f05de9bef2168`
Date: 2026-06-17 UTC

## Scope

Added bounded produced-PDF page `/Lang` provenance to `PdfEngineHandoff` page
display metadata. Fake-run inspection now preserves string and name-form page
language values, includes language-only page display records, propagates them
through sequence summaries, and emits a `pdf-byte-page-languages:<count>`
diagnostic.

## Coverage

Extended the existing PDF page display metadata fixture with two page-level
language forms:

- `/Lang (en-US)`
- `/Lang /fr-CA`

The fixture now asserts the exposed `pdfPageDisplayMetadata`, final sequence
metadata, and language diagnostic count.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test files, 2638 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 258 test files, 175027 assertions, 0 failures

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
