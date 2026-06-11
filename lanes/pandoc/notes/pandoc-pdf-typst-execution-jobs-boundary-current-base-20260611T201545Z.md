# Pandoc PDF/Typst Execution Jobs Boundary Provenance

Slice: `pandoc-pdf-typst-execution-jobs-boundary-current-base-20260611T201545Z`

Bead: `plib-zqiya`

Base: `origin/main` `dee522c64` after rebase.

## Scope

Mapped one bounded native PHP `PdfEngineHandoff` case for Typst `--jobs`/`-j`
execution job policy provenance. The plan now records selected jobs value,
`auto`/fixed/invalid mode, numeric job count when bounded, validation issues,
override history, and deterministic diagnostics. `fakeRun()` and
`fakeRunSequence()` carry the same inert provenance through artifact review
metadata without executing Pandoc, Typst, TeX/PDF engines, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.

## Non-Overlap

This does not repeat accepted Typst boundary work for root reads, package/cache
paths, package dependency sidecars, input variables, creation timestamps, output
format, PDF standards/pages/tags, feature gates, diagnostics, timings, system or
embedded fonts, open-output side effects, or warning source provenance. It owns
only the execution parallelism option boundary.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 file, 1806 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 files, 65891 assertions, 0 failures.

## Accounting

Mapped denominator: 3209.

Added: `mappedTypstExecutionJobsBoundaryProvenanceCases = 1`,
`typstExecutionJobsBoundaryProvenanceAssertions = 12`.

Lane `phpPass`: 3115 -> 3116; `phpFail` remains 0.
