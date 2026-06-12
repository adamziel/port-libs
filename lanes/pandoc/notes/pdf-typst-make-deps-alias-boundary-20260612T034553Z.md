# Pandoc PDF Typst Make Deps Alias Boundary Provenance

Slice: PDF/Typst boundary provenance core blocker `plib-09g8j` on current base
`acb8fb36b3`.

## Summary

- Captured Typst dependency sidecar option spelling for `--deps` and
  `--make-deps` while preserving the normalized dependency output path handoff.
- Added `--make-deps` alias provenance diagnostics and option history exposure
  when the selected dependency sidecar spelling differs from the canonical
  `--deps` path.
- Summarized dependency-output option history with selected option spelling and
  per-option counts for package-review queues.
- Added focused fake-run coverage for `--make-deps=...` sidecar artifacts,
  produced-artifact hashing, review propagation, and sequence propagation.

## Direct-Format Accounting

- `phpPass`: `3188 -> 3189`
- Added cases: `mappedTypstMakeDepsAliasBoundaryCases = 1`
- Added assertions: `typstMakeDepsAliasBoundaryAssertions = 21`

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 2081 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 70500 assertions, 0 failures

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.
