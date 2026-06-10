# pandoc-pdf-javascript-action-safety-current-base-20260610T1220Z

Slice: `pandoc-pdf-javascript-action-safety-current-base-20260610T1220Z`

This slice extends `PdfEngineHandoff` fake-runner PDF byte inspection to expose
inert JavaScript action safety metadata for reviewer handoff. Produced PDF
review packets now report:

- `pdfJavaScriptActions` for catalog `OpenAction`, annotation additional
  actions, and JavaScript name-tree entries
- `pdfJavaScriptActionPolicy` with script counts, source categories, byte
  totals, token counts, URL counts, `submitForm` counts, missing-script counts,
  and review-policy issues
- sequence-level `finalPdfJavaScriptActions` and
  `finalPdfJavaScriptActionPolicy`
- diagnostics for action counts, policy status, source categories, token flags,
  URL counts, submit form counts, and issue counts

Script bodies are not exposed. The handoff stores script byte counts and
SHA-256 hashes plus bounded token flags such as `launch-url`, `submit-form`,
`field-write`, `mail-doc`, `dynamic-eval`, and `timer`.

This stays bounded to produced-PDF fake-runner metadata. It does not execute
JavaScript and does not invoke Pandoc, TeX/PDF engines, Typst, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 file, 1472 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 60005 assertions, 0 failures

Status delta:

- `lane-status.json` `phpPass`: `2963 -> 2964`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3130 -> 3131`
- `pdfEngineHandoffCoreCases`: `13 -> 14`
- `mappedPdfEngineHandoffCoreCases`: `13 -> 14`
- `pdfEngineHandoffCoreAssertions`: `116 -> 128`
- `mappedPdfJavaScriptActionSafetyCases`: `0 -> 1`
- `pdfJavaScriptActionSafetyAssertions`: `0 -> 12`
