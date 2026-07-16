# Pandoc PDF/Typst Package Storage Environment Shadow

Slice: `pandoc-pdf-typst-package-storage-environment-shadow`

Issue: `plib-ghr7a`

This slice extends native PHP `PdfEngineHandoff` Typst boundary provenance for
package storage environment variables that are present but shadowed by explicit
CLI options.

Behavior:

- `TYPST_PACKAGE_PATH` is preserved as `packagePathEnvironment` when
  `--package-path` selects a different package path.
- `TYPST_PACKAGE_CACHE_PATH` is preserved as `packageCacheEnvironment` when
  `--package-cache` or `--package-cache-path` selects a different cache path.
- Shadowed environment records retain path safety, source environment variable,
  selected CLI value, and review issues.
- Boundary diagnostics, `typstBoundarySummary`, `packageStoragePolicy`,
  fake-run artifact review, and fake-run sequence summaries carry the shadow
  metadata without executing Typst or any PDF engine.

Accounting:

- `phpPass`: `3469 -> 3470`
- `phpFail`: remains `0`
- mapped denominator: `3410 -> 3411`
- `mappedTypstPackageStorageEnvironmentShadowCases`: `1`
- `typstPackageStorageEnvironmentShadowAssertions`: `19`

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2442 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 81250 assertions, 0 failures`

No Pandoc binary, Cabal/Haskell runner, Typst/PDF engine, browser renderer,
external validator, online service, live provider test, or live-service provider
test was invoked.
