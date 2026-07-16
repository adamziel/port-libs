# PDF Web Capture Source Policy

Bead: plib-ns6oq
Date: 2026-06-17

## Scope

- Added native `pdfWebCapturePolicy` summaries for parsed PDF SpiderInfo Web Capture commands.
- Tracks source URL schemes, remote/file/missing source counts, page references, unresolved page references, command links, unresolved command links, and maximum capture depth.
- Exposes the policy through `fakeRun` diagnostics and `fakeRunSequence` final state without invoking Pandoc, Typst, TeX/PDF engines, browsers, or external validators.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
- JSON status/manifest validation
- Conflict-marker scan
- `git diff --check`
