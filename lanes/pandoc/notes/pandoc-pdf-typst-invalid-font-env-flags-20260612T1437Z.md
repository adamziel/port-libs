# Pandoc PDF/Typst Invalid Font Environment Flags - 20260612T1437Z

Slice: `plib-36nom`, PDF/Typst boundary provenance.

This slice extends native PHP `PdfEngineHandoff` Typst boundary provenance for
invalid font-access environment flags:

- `TYPST_IGNORE_SYSTEM_FONTS`
- `TYPST_IGNORE_EMBEDDED_FONTS`

When either environment value is present but not a recognized boolean token,
the handoff now preserves a review record instead of dropping the value. The
record keeps default font access state, environment variable source/value,
issue diagnostics, boundary summary issue counts, fake-run artifact review, and
final sequence summaries.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2108 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 71818 assertions, 0 failures`

Accounting:

- Adds `mappedTypstEnvironmentFlagBoundaryCases = 1`.
- Adds `typstEnvironmentFlagBoundaryAssertions = 16`.
- Moves `phpPass` `3226 -> 3227`; `phpFail` remains `0`.

No Pandoc, Typst, TeX/PDF engines, browser renderers, office suites,
`zip`/`unzip`, Node tooling, external validators, online services, live provider
tests, or live-service provider tests were run.
