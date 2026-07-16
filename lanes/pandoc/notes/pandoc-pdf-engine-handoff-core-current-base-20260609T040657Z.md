# Pandoc PDF Engine Handoff - Signature Appearance Byte Ranges

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T040657Z`
- Base accepted HEAD: `39b1c5d5b6751a4cd8edd906dabeef64d6d0fc2e`

## Scope

This slice adds bounded native PHP fake-runner diagnostics for PDF signature widget appearance byte ranges. `PdfEngineHandoff` now joins extracted AcroForm signature dictionaries to widget annotation appearance records, records the appearance object and appearance stream byte spans from the produced PDF bytes, and reports whether those spans are covered by the signature `/ByteRange`.

The behavior is review metadata only. It does not validate cryptographic signatures, render appearance streams, execute Pandoc, execute TeX/PDF engines, execute Typst, run browser renderers, run external PDF validators, run office tools, run zip/unzip, or call online services.

## Evidence

- Rework notes checked: no current `port-pandoc-*.needs-lane-rework.md` note was present for this lane before editing.
- Baseline focused test before this patch: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 1209 assertions, 0 failures`.
- Final syntax checks passed:
  - `php -l lanes/pandoc/src/PdfEngineHandoff.php`
  - `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- Final focused test passed: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` with `1 test files, 1219 assertions, 0 failures`.
- Final example smoke passed: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`.
- Lane JSON checks passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `2275 -> 2276`.
- `UPSTREAM_TEST_MANIFEST.json` `mapped`: `2677 -> 2678`.
- `pdfEngineHandoffCoreCases`: `12 -> 13`.
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 118`.
- Focused `PdfEngineHandoffTest.php` assertions: `1209 -> 1219`.

## Non-Overlap

This slice does not repeat PDF argv planning, templates, source/resource validation, engine logs, TeX sidecars, output metrics, xref/object-stream inventory, page trees, outlines, page boxes, page labels, info/XMP/output intents, catalog/viewer policy, name trees, destinations, tagging/parent-tree/ID-tree policy, annotation appearance extraction, stream filters, rich media, embedded/associated files, marked-content summaries, optional content, encryption preflight, signature byte-range policy, signature revision windows, signature seed/lock/FieldMDP policy, active actions, DSS extraction, or conformance review. The new behavior is limited to joining existing signature and annotation appearance metadata to produced-byte object and stream span coverage.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `PdfEngineHandoff` object parsing, existing signature extraction, existing annotation appearance extraction, and existing byte-range policy helpers. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task that would require a hydrated Pandoc checkout and Haskell test executables.

## Follow-Up

Next non-overlapping PDF engine handoff work should target tagged-PDF ID-tree policy review or missing visual-signature appearance policy, with focused PHP tests and without executing external renderers or validators.
