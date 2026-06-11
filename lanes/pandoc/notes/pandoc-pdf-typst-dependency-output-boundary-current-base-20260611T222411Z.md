# PDF/Typst dependency output boundary provenance

Slice: plib-d000x

Base: e4b151737ee24e60827e641ea3d7a6ebc8c5043a

PdfEngineHandoff now preserves Typst dependency sidecar output path boundary provenance for `--deps` and `--make-deps`. The handoff records selected safe and unsafe dependency sidecar paths, alias override history, plan diagnostics, fake-run artifact review, and sequence summaries without invoking Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed 1 test file, 1834 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 66648 assertions, 0 failures.

Lane status: `phpPass` moves 3133 -> 3134, `phpFail` remains 0.

Mapping delta:

- `mappedTypstDependencyOutputBoundaryCases`: 1
- `typstDependencyOutputBoundaryAssertions`: 14
