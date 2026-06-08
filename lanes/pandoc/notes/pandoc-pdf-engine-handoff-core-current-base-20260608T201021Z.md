# Pandoc PDF Engine Handoff Current-Base Structure Class Usage

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T201021Z`
Base accepted HEAD: `70d557c28daa508cdd36e70149395d52ed3b6a44`
Date: 2026-06-08 UTC

## Behavior

`PdfEngineHandoff` now exposes bounded tagged-PDF structure class usage from
fake-produced PDF bytes:

- `pdfStructureClassUsage` on single fake runs.
- `finalPdfStructureClassUsage` on fake-run sequences.
- `/StructElem /C` class names from both single-name and array forms.
- ClassMap attribute-count summaries for each referenced class.
- Missing class-name diagnostics when a structure element references a class
  absent from `/StructTreeRoot /ClassMap`.

This is metadata handoff only. It does not validate PDF/UA conformance, execute
JavaScript, render PDFs, or shell out to Pandoc or a PDF engine.

## Evidence

- Rework-note check: current top-level `port-pandoc-*.needs-lane-rework.md`
  glob returned no files.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 945 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 956 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed.
- PHP lint passed for `lanes/pandoc/src/PdfEngineHandoff.php`,
  `lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- Adds one focused PHP PASS case.
- `PdfEngineHandoffTest.php` focused coverage increases by `+11` assertions.
- `lane-status.json` `phpPass`: `1795 -> 1796`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2215 -> 2216`.
- PDF engine inventory: `pdfEngineHandoffCoreCases` and
  `mappedPdfEngineHandoffCoreCases` move `12 -> 13`;
  `pdfEngineHandoffCoreAssertions` moves `108 -> 119`.

## Non-Overlap

This does not repeat earlier PDF-engine handoff slices for PDF engine argv,
template/header/resource planning, log/sidecar diagnostics, generated output
metrics, multipass reruns, bibliography sidecars, xref/object streams, page
metadata, outlines, document-info/XMP/PDF-A/PDF-UA metadata, output intents,
URI base, catalog requirements, LegalAttestation, DSS, signatures, AcroForm,
active actions, optional content, annotations, embedded files, marked-content
properties, parent-tree mappings, structure element accessibility metadata,
structure attributes, or ClassMap attribute extraction. The new surface is the
per-structure-element `/C` class usage link to existing ClassMap metadata.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP
`PdfEngineHandoff` object parser, structure element parser, ClassMap attribute
summarizer, fake-runner result wiring, and WordPress PDF review-packet example.
Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff,
JavaScript execution, external PDF validators, online services, live provider
tests, and live-service provider tests remain out of scope for this lane slice.

## Follow-Up

Next PDF-engine work should choose a non-overlapping fake-produced PDF metadata
gap such as deeper RoleMap/ClassMap interaction, tagged structure IDTree
details, or incremental signature revision provenance while preserving the
same no-engine fake-runner boundary.
