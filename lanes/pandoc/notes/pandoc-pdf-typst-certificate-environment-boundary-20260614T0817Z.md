# Pandoc PDF/Typst Certificate Environment Boundary

Slice: `plib-k4zho`, PDF/Typst boundary provenance.
Base after rebase: `74ec69378d`.

This slice extends native PHP `PdfEngineHandoff` Typst certificate provenance
for `TYPST_CERT`, matching Typst CLI `--cert` environment fallback behavior.
When no explicit `--cert` option is present, the environment certificate is
recorded as an environment-sourced certificate boundary entry. When an explicit
`--cert` option is present, `TYPST_CERT` is retained as shadowed environment
provenance with path safety, selected option, and certificate policy metadata.

Focused coverage verifies:

- safe selected `TYPST_CERT` certificate provenance;
- unsafe external `TYPST_CERT` shadowed by explicit `--cert`;
- certificate policy counts, environment variable accounting, diagnostics,
  artifact-provenance handoff, and fake-run sequence carry-forward.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2484 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 82163 assertions, 0 failures`

Accounting:

- Adds `mappedTypstCertificateEnvironmentBoundaryCases = 2`.
- Adds `typstCertificateEnvironmentBoundaryAssertions = 31`.
- Moves `phpPass` `3494 -> 3495`; `phpFail` remains `0`.

No Pandoc, Typst, TeX/PDF engines, browser renderers, office suites,
`zip`/`unzip`, Node tooling, external validators, online services, live
provider tests, or live-service provider tests were run.
