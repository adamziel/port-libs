# PDF/Typst Package Unsupported Reason Reporting

Date: 2026-06-13
Issue: plib-4bs3v
Base: `6f88e1cb7a`

## Slice

`PdfEngineHandoff` fake runs now extend `typstPackageDependencyPolicy` with
deterministic unsupported package reason reporting. Package dependencies keep
the source-class provenance from `plib-15bdf`, and policy summaries now expose
per-package `unsupportedPackageReasons`, aggregate `unsupportedReasonCounts`,
and `typst-package-unsupported-reason:*` diagnostics for preview registry,
Typst registry, and custom namespace dependencies.

This closes one bounded PDF/Typst package-policy reporting gap beyond source
class counts. It does not attempt full Typst/PDF output parity.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 2237 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 75511 assertions, 0 failures

No Pandoc binary, Cabal/Haskell runner, Typst/PDF engine, browser renderer,
Node tooling, online service, live provider test, or external validator was
invoked.

## Remaining Gaps

PDF/Typst remains unsupported for real output parity under the current native
PHP no-external-engine policy. Remaining work includes a native Typst reader,
stronger output comparison evidence, and broader PDF engine behavior coverage
outside this metadata-only fake-runner boundary.
