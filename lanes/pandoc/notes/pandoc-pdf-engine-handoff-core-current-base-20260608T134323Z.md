# PDF Engine Handoff Core Current Base - AcroForm Action Targets

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T134323Z`
Accepted base: `6cbd04ea092db01b59e065a537847345444dffe6`

## Scope

This slice adds bounded native fake-runner inspection for produced-PDF AcroForm action target lists. `PdfEngineHandoff` now exposes `pdfFormFieldActionTargets` and `finalPdfFormFieldActionTargets` for field-level `SubmitForm`, `ResetForm`, and `ImportData` actions when `/Fields` or `/Flags` are present.

The handoff records:

- owning form field name/object/type metadata;
- trigger/source path, including `A` and `AA.*` triggers;
- action type and bounded target URI/file value;
- `/Flags` integer plus named flag bits for submit/reset actions;
- `/Fields` target names resolved from literal strings and field references;
- include-listed, exclude-listed, or all-fields selection policy diagnostics.

No PDF action, JavaScript, form submission, renderer, validator, or PDF engine is executed.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 865 assertions, 0 failures`.
- Red-first: after adding the fixture, `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` failed with `1 test files, 867 assertions, 1 failures` because `pdfFormFieldActionTargets` was not exposed.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 876 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed.
- PHP lint passed for `lanes/pandoc/src/PdfEngineHandoff.php`, `lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace: `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- Adds one named focused PHP PASS case.
- `PdfEngineHandoffTest.php` focused assertions increase from `865` to `876` (`+11`).
- `lane-status.json` `phpPass` increases from `1656` to `1657`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped` increases from `2076` to `2077`.
- PDF engine handoff inventory moves from `12` to `13` mapped cases and from `108` to `119` focused assertions.

## Non-Overlap

This does not repeat prior PDF-engine slices for XMP/PDF-A metadata, output intents, tagged structure, URI base, page display metadata, LegalAttestation metadata, active action chains, AcroForm dictionary/calculation-order metadata, page resource source inventory, transition direction, or generic form-field action type summaries. It narrows the new behavior to action target lists and flag/field-selection semantics on AcroForm field actions.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP `PdfEngineHandoff` PDF object parsing, AcroForm field traversal, PDF value parsing, fake-runner result wiring, and WordPress PDF handoff example. Real Pandoc execution, TeX/PDF engines, Typst, browser renderers, roff, JavaScript/action execution, external validators, and online services remain out of scope for this lane slice.

## Follow-Up

Next PDF-engine work should choose a non-overlapping produced-PDF handoff gap such as AcroForm calculation/action dependency graphs, submit-format export policy, signature appearance byte ranges, or JavaScript safety metadata, while preserving the no-engine fake-runner boundary.
