# PDF Engine Handoff Core Current Base - Missing Dependency Diagnostics

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T062540Z`
Base accepted HEAD: `fc8eeee0d58103faabecc24a17572b78d812884d`
Date: 2026-06-09 UTC

## Behavior

`PdfEngineHandoff::fakeRun()` now extracts structured missing dependency
diagnostics from synthetic engine logs without executing any PDF renderer:

- missing TeX/input files such as `review-style.sty`;
- missing OpenType/system font names reported by fontspec/XeTeX-style logs;
- missing font metric names from bounded `kpathsea` `mktextfm` lines;
- per-kind counters and diagnostics for WordPress export triage packets.

This is a fake-runner diagnostic handoff only. It does not execute Pandoc,
Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external template engines,
TeX/PDF engines, Typst, browser renderers, online services, live provider tests,
or live-service provider tests.

## Evidence

Baseline focused test before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1333 assertions, 0 failures`

Red-first focused test after adding the missing-dependency fixture:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1336 assertions, 1 failures`
- Failure: `engineMissingDependencies` was `NULL`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1343 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- Result: `pdf engine handoff self-test ok`

Additional verification commands are recorded in the final handoff output.

## Status Delta

- `lane-status.json` `phpPass`: `2443 -> 2444`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2831 -> 2832`.
- `pdfEngineHandoffCoreCases`: `12 -> 13`.
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 118`.

## Non-Overlap

This slice avoids the recent OPC XML relationship package consistency slice and
the accepted PDF handoff work for catalog requirements, PDF/A/PDF/UA policy,
DSS/VRI, legal attestation, web capture, linearization, xref/object streams,
page/action/form/signature/annotation metadata, sidecar hashing, recorder
files, source maps, missing executable diagnostics, and multipass rerun
clearing. It owns only structured missing dependency extraction from fake
renderer logs.

## Dependency Closure

No new native support component is needed. The implementation reuses the native
PHP `PdfEngineHandoff` fake-runner log parsing and the existing WordPress PDF
engine handoff example.

Full renderer parity remains outside this slice and would require explicit
authorization for TeX/PDF engines, Typst, browser renderers, roff, or upstream
Pandoc/Haskell runner work.
