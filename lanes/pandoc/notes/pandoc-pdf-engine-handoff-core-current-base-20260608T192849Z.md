# Pandoc PDF Engine Handoff Current-Base Parent Tree

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T192849Z`
Base accepted HEAD: `520f0ce7b08b30848beed1a62b07a69292c33e03`
Date: 2026-06-08 UTC

## Behavior

`PdfEngineHandoff` now exposes bounded produced-PDF structure parent-tree number mappings from fake-runner output bytes:

- `pdfStructureParentTree` / `finalPdfStructureParentTree` entries for `/StructTreeRoot /ParentTree`.
- `/Nums` MCID keys, value kind, value object, array parent counts, structure references, missing references, node source, and `/Limits`.
- Diagnostics for parent-tree entry count, array-valued entries, null parent slots, and missing structure references.

This is a metadata handoff only. It does not execute Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff renderers, JavaScript, external PDF validators, online services, live provider tests, or live-service provider tests.

## Evidence

- Baseline current accepted PDF handoff focused coverage before this slice was `PdfEngineHandoffTest.php` at `898 assertions / 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 910 assertions, 1 failures`
  - Failure: the new parent-tree fixture returned `null` for `pdfStructureParentTree`.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 916 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - Result: `pdf engine handoff self-test ok`
- PHP lint passed for `lanes/pandoc/src/PdfEngineHandoff.php`, `lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace: `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- Adds one focused PHP PASS case for produced-PDF tagged structure parent-tree mappings.
- `PdfEngineHandoffTest.php` final focused coverage adds `+18` assertions for this slice.
- `lane-status.json` `phpPass`: `1750 -> 1751`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2166 -> 2167`.
- PDF engine inventory: `pdfEngineHandoffCoreCases` and `mappedPdfEngineHandoffCoreCases` move `12 -> 13`; `pdfEngineHandoffCoreAssertions` moves `108 -> 126`.

## Non-Overlap

This does not repeat prior PDF-engine slices for XMP/PDF-A/PDF-UA metadata, output intents, page display/timings/viewports, URI base, catalog requirements, LegalAttestation, DSS, active actions, signatures, AcroForm, optional content, annotations, embedded files, page resources, structure elements, or structure attributes. It only adds the parent-tree number mapping that connects marked-content IDs to structure elements for review handoff.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP bounded PDF object parser, dictionary/reference resolver, number-tree traversal, fake-runner result wiring, and WordPress PDF handoff example. Full PDF/UA validation and real renderer parity remain outside this slice and require explicit authorization for Pandoc/Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff renderers, external PDF validators, JavaScript, online services, live provider tests, or live-service provider tests.

