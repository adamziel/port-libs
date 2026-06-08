## pandoc-pdf-engine-handoff-core-current-base-20260608T114654Z

Base accepted HEAD: e4ee6a1add43b78244f223d2bcb7f079d1aafd59

Scope:
- Added native produced-PDF page transition `directionLabel` handoff metadata while preserving the raw `/Di` `direction` token.
- Added `pdf-byte-page-transition-direction:<label>:<count>` diagnostics for bounded fake-runner PDF byte inspection.
- Updated the WordPress PDF handoff smoke expectations so review packets expose the new label for transition direction audits.

Non-overlap:
- This slice extends the already accepted page-timing/transition handoff without repeating page labels, page display metadata, page viewports, output intents, URI base, tagged PDF, annotations, actions, optional content, signatures, embedded files, associated files, or upstream-runner dependency audit slices.
- No Pandoc, TeX/PDF engine, Typst, browser renderer, roff, external PDF validator, online service, live provider test, or live-service provider test was executed.

Focused evidence:
- Baseline before this patch: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 846 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 854 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed with `pdf engine handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/PdfEngineHandoff.php`, `lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace check passed: `git diff --check -- lanes/pandoc`.

Status delta:
- `lane-status.json` `phpPass`: `1629` -> `1630`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2048` -> `2049`.
- `pdfEngineHandoffCoreCases`: `12` -> `13`.
- `pdfEngineHandoffCoreAssertions`: `108` -> `116`.

Dependency closure:
- No new support component is needed. The slice reuses the existing native `PdfEngineHandoff` fake-produced PDF byte parser and bounded WordPress PDF handoff example.
- Full rendered-PDF parity remains outside this slice and requires separately authorized renderer/Pandoc/TeX evidence.
