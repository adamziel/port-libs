# Pandoc PDF/Typst Feature Environment Shadow Provenance

Slice: `plib-tiwfk`, PDF/Typst boundary provenance.
Base after rebase: `874c3d1d0c`.

This slice extends native PHP `PdfEngineHandoff` Typst feature-gate boundary
provenance for `TYPST_FEATURES` values that are present in the engine
environment but shadowed by explicit `--features` CLI options.

Behavior:

- Preserves shadowed `TYPST_FEATURES` as `featureGateEnvironment` instead of
  dropping the environment input.
- Records the environment source tag, selected CLI feature value, normalized
  feature list, invalid/empty feature token issues, and
  `features-environment-shadowed`.
- Carries the shadow record through plan diagnostics, fake-run artifact
  provenance, boundary summaries, and fake-run sequence summaries without
  executing Typst or any PDF engine.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2500 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 82232 assertions, 0 failures`

Accounting:

- `phpPass`: `3497 -> 3498`
- `phpFail`: remains `0`
- Adds `mappedTypstFeatureEnvironmentShadowCases = 1`
- Adds `typstFeatureEnvironmentShadowAssertions = 16`

No Pandoc binary, Cabal/Haskell runner, Typst/PDF engine, browser renderer,
external validator, online service, live provider test, or live-service
provider test was invoked.
