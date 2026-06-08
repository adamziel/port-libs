# Pandoc PDF Engine Handoff Field Actions

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T094523Z`

Accepted base: `e8dffc9f0d3aa735a6dd8abc60956f05dbfe08da`

## Scope

This slice adds bounded produced-PDF AcroForm field-action handoff diagnostics to the native PHP PDF engine fake runner. It inspects field-level primary `/A` actions and additional `/AA` trigger actions from AcroForm field trees, including inherited field names and field types, action sources, action targets, and bounded JavaScript digest metadata.

The implementation does not execute field actions and does not invoke Pandoc, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, JavaScript, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This avoids the already covered PDF engine clusters for generic active-action chain summaries, legal attestation permissions, annotation actions, signature metadata, page-display metadata, tagged structure metadata, URI base metadata, output intents, and XMP/PDF-A packet handoff. The new behavior is specifically field-scoped AcroForm action review metadata surfaced through `pdfFormFieldActions`, `pdfFormFieldActionTypes`, and final fake-runner sequence summaries.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` rework note existed for this lane before implementation.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 839 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - Result: `pdf engine handoff self-test ok`
- PHP lint passed for changed PHP files.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1601 -> 1602`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2020 -> 2021`
- Focused test delta: +1 PHP PASS case / +10 focused assertions in `PdfEngineHandoffTest.php`.

## Dependency Closure

No new support component is needed. The slice reuses `PdfEngineHandoff` PDF object parsing, action summarization, AcroForm field-tree traversal, focused PDF engine tests, and the WordPress PDF handoff example.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout and reviewed non-mutating Cabal plan. That remains out of scope for this PDF engine support-library slice.

## Next

A useful follow-up would stay in PDF engine handoff and add a non-overlapping produced-PDF diagnostic such as form action target-list flags, calculation-order review metadata, or richer page lifecycle/action validation, still bounded to native byte inspection.
