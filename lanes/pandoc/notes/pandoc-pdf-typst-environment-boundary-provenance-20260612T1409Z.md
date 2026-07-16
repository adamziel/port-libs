# Pandoc PDF/Typst environment boundary provenance - 20260612T1409Z

Slice: `plib-uyzmh`, PDF/Typst boundary provenance.

This slice extends native PHP `PdfEngineHandoff` planning with inert
`engineEnvironment` provenance for Typst compile environment variables that can
affect boundary behavior:

- `TYPST_ROOT`
- `TYPST_FONT_PATHS`
- `TYPST_PACKAGE_PATH`
- `TYPST_PACKAGE_CACHE_PATH`
- `TYPST_IGNORE_SYSTEM_FONTS`
- `TYPST_IGNORE_EMBEDDED_FONTS`
- `TYPST_FEATURES`
- `SOURCE_DATE_EPOCH`

When the matching CLI option is absent, these environment defaults are recorded
in `typstBoundaryProvenance`, including environment source tags, font path
policy review, environment variable counts, feature/timestamp normalization,
font access controls, fake-run artifact review, and final sequence summaries.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2092 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 71694 assertions, 0 failures`

Accounting:

- Adds `mappedTypstEnvironmentBoundaryCases = 1`.
- Adds `typstEnvironmentBoundaryAssertions = 22`.
- Moves `phpPass` `3223 -> 3224`; `phpFail` remains `0`.

No Pandoc, Typst, TeX/PDF engines, browser renderers, office suites,
`zip`/`unzip`, Node tooling, external validators, online services, live provider
tests, or live-service provider tests were run.
