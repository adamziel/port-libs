# Pandoc PDF/Typst Attached Short Jobs Boundary Provenance

Slice: `plib-mvcec`, PDF/Typst boundary provenance.

Base: current `origin/main` `896de3581753df8903e08fd4a047ee034d7d90fb`.

`PdfEngineHandoff` now preserves attached short Typst execution job options such
as `-j4` as inert boundary provenance. The shared engine-option parser records
the selected value, fixed job mode, numeric job count, diagnostics, fake-run
artifact review metadata, and sequence summaries without invoking Pandoc, Typst,
TeX/PDF engines, browser renderers, external validators, online services, live
provider tests, or live-service provider tests.

This slice does not repeat broader Typst jobs validation, root/package/cache
paths, input variables, output formats, PDF export options, diagnostics,
timings, package dependency sidecars, font policies, open-output side effects,
warning source provenance, or produced-PDF byte parsing. It owns only attached
short-option value extraction for the existing execution-jobs boundary surface.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1898 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 68081 assertions, 0 failures`

Lane status: `phpPass` moves `3156 -> 3157`; `phpFail` remains `0`.
