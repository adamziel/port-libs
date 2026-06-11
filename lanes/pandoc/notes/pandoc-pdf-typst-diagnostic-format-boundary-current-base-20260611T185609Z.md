# pandoc-pdf-typst-diagnostic-format-boundary-current-base-20260611T185609Z

Slice: `plib-3fsc1`, PDF/Typst boundary provenance.
Required base after final rebase: `f99ec6e05`.

## Change

`PdfEngineHandoff` now preserves Typst `--diagnostic-format` boundary
provenance in `typstBoundaryProvenance`.

The selected format is recorded as `diagnosticFormat`; invalid or unsupported
discarded declarations remain visible in `diagnosticFormatHistory`; repeated
singleton declarations add an override entry with
`diagnostic-format-boundary-overridden`. Plans emit
`typst-diagnostic-format:*`, `typst-boundary-overrides:*`, and
`typst-boundary-issues:*` diagnostics, and fake-run artifact review plus
sequence summaries carry the same provenance.

This slice does not invoke Pandoc, Typst, TeX/PDF engines, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 1774 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 65355 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3099 -> 3100`.
- Added one focused `PdfEngineHandoffTest` PASS case for Typst diagnostic
  format boundary provenance.
- Added `mappedTypstDiagnosticFormatBoundaryCases = 1`.
- Added `typstDiagnosticFormatBoundaryAssertions = 11`.

## Scope

This does not repeat accepted Typst root, package/cache, input override,
feature gate, system/embedded font, PDF export, PDF standard, timings,
dependency sidecar, output format, or root read-boundary slices. It is limited
to the CLI diagnostic-format boundary option and its plan/fake-run provenance.
