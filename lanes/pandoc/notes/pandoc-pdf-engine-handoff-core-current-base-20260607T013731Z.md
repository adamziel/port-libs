# Pandoc PDF Engine Handoff Current-Base Slice 2026-06-07

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260607T013731Z`
- Accepted base: `0609e7973a6833bc3fc73d13f143a74d01c61239`
- Scope: bounded native PHP produced-PDF fake-runner preflight.

## Behavior

`PdfEngineHandoff` now extracts the first-object `/Linearized` dictionary from fake-produced PDF bytes without running Pandoc, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, or online services.

The handoff records:

- linearization object reference;
- `/Linearized` version;
- declared `/L` file length and whether it matches actual bytes;
- `/H` hint-table offset/length pairs;
- primary hint-table offset/length;
- first-page object `/O`;
- first-page end offset `/E`;
- page count `/N`;
- main xref offset `/T`.

Fake-run diagnostics now include `pdf-byte-linearized`, linearized version, linearized page count, hint-table count, and declared-length mismatch evidence. Multipass fake-runner summaries carry the final value as `finalPdfLinearization`.

## Evidence

- Baseline focused command: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` -> `1 test files, 686 assertions, 0 failures`.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` -> `1 test files, 696 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` -> `pdf engine handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/PdfEngineHandoff.php`, `lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1429` -> `1430`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1845` -> `1846`.
- `pdfEngineHandoffCoreCases`: `11` -> `12`.
- `mappedPdfEngineHandoffCoreCases`: `11` -> `12`.
- `pdfEngineHandoffCoreAssertions`: `106` -> `116`.

## Non-Overlap

This slice does not touch accepted PDF sidecar/log, SyncTeX, recorder, transcript, trailer/xref/object-stream, page tree/boxes/display/timings/viewports/content-stream, font/image/form-XObject/graphics-state, page-label, document-info, XMP/PDF-A, output-intent, URI base, viewer-preference, tagging/structure, annotation, RichMedia, embedded-file, optional-content, AcroForm, signature, catalog permission, active-action, collection, thread, or encryption surfaces. It is limited to the linearized-PDF first-object dictionary handoff.

## Dependency Closure

No new support component is needed. This reuses native PHP fake-runner byte inspection and existing bounded PDF dictionary token helpers. Full upstream Pandoc runner parity remains blocked on a hydrated pinned upstream checkout and explicit authorization for Haskell/Cabal solver/build/runner work, not on this local PDF linearization primitive.
