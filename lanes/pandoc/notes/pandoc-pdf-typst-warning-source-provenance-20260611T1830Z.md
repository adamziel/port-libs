# Pandoc PDF/Typst Warning Source Provenance

Slice: `pandoc-pdf-typst-warning-source-provenance-20260611T1830Z`
Lane: `pandoc`

## Scope

This slice preserves structured Typst warning source provenance in the native PHP
`PdfEngineHandoff` fake runner without executing Pandoc, Typst, TeX/PDF engines,
browser renderers, external validators, online services, live provider tests, or
live-service provider tests.

The new review surface records:

- warning messages parsed from fake-run stdout, stderr, and engine log text;
- source file, line, column, and end-column spans from Typst diagnostic snippets;
- hint lines attached to each warning;
- source-file rollups;
- outside-root warning source paths when a safe Typst `--root` boundary is declared;
- artifact review and fake-run sequence propagation.

## Evidence

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1776 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 65294 assertions, 0 failures`
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `3098 -> 3099`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3203 -> 3204`
- `mappedTypstWarningSourceProvenanceCases`: `1`
- `typstWarningSourceProvenanceAssertions`: `15`
- Focused `PdfEngineHandoffTest.php`: one new PASS case.

## Non-Overlap

This does not repeat accepted PDF/Typst slices for argv planning, root/font/package
boundaries, package-cache dependency normalization, dependency output targets,
output format policy, root read-boundary policy, input-variable provenance,
creation timestamps, feature gates, PDF standards, PDF page export controls, or
PDF byte inspection.

The new surface is limited to structured Typst warning diagnostics already present
in fake-run text streams, plus review propagation.
