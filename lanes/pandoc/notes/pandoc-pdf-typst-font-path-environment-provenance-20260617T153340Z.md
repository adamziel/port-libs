# PDF/Typst Font Path Environment Provenance

Issue: plib-2zucv

This slice keeps the work inside `lanes/pandoc` and covers a native PHP PDF/Typst boundary provenance gap. `PdfEngineHandoff` now keeps `TYPST_FONT_PATHS` entries in `typstBoundaryProvenance` even when the Typst plan also carries CLI `--font-path` entries, preserving environment source metadata and boundary review issues for mixed handoffs.

The focused fixture preserves both CLI and environment font paths, verifies policy counts, system-font summaries, fake-run artifact provenance, and sequence propagation. No Pandoc, Typst, TeX/PDF engines, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests are invoked.

Accounting adds:

- `mappedTypstFontPathEnvironmentProvenanceCases = 1`
- `typstFontPathEnvironmentProvenanceAssertions = 18`

Verification after rebase onto current main `125d4120dc`:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` (1 file / 2714 assertions / 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (258 files / 175103 assertions / 0 failures)
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- conflict-marker scan and `git diff --check`
