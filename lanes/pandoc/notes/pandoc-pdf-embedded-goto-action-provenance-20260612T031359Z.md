# pandoc-pdf-embedded-goto-action-provenance-20260612T031359Z

Slice: `plib-6h4ie`, PDF/Typst boundary provenance core blocker.
Base: `origin/main` `643ed855c0`.

## Scope

This slice adds bounded native PHP produced-PDF action provenance for embedded
go-to actions (`/S /GoToE`) in `PdfEngineHandoff`. The handoff now classifies
embedded-file navigation actions alongside existing URI, Launch, SubmitForm,
ResetForm, ImportData, JavaScript, and named actions without executing Pandoc,
Typst, TeX/PDF engines, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

## Change

- `PdfEngineHandoff` now treats `/GoToE` as an active action type for catalog,
  annotation, and page additional-action sources.
- Active/page action policies now add `embeddedFileActionCount` and
  `embedded-file-action` review issues only when embedded-file actions are
  present, preserving existing policy shapes for non-GoToE packets.
- Target summaries preserve bounded file-spec, target-dictionary, and
  destination provenance such as `F=...`, `T=...`, and `D=...`.
- `PdfEngineHandoffTest.php` adds one focused fake-produced PDF fixture covering
  catalog `OpenAction`, file-attachment annotation `A`, page-close `AA.C`, and
  fake-run sequence propagation.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
  - `No syntax errors detected in lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2008 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69751 assertions, 0 failures`

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
