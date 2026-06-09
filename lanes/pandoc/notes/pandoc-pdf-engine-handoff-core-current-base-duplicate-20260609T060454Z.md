# Pandoc PDF Engine Handoff Current-Base RoleMap Usage

Slice: `pandoc-pdf-engine-handoff-core-current-base-duplicate-20260609T060454Z`
Base accepted HEAD: `11b5789183ebb8ab34ff922479caf161e9cc4881`
Date: 2026-06-09 UTC

## Behavior

`PdfEngineHandoff` now exposes bounded tagged-PDF RoleMap usage from
fake-produced PDF bytes:

- `pdfStructureRoleMapUsage` on single fake runs.
- `finalPdfStructureRoleMapUsage` on fake-run sequences.
- Per-`/StructElem /S` role chains through `/StructTreeRoot /RoleMap`.
- Terminal mapped-role summaries and standard-structure-role coverage.
- Review diagnostics for custom terminal roles and bounded RoleMap cycles.

This is metadata handoff only. It does not validate PDF/UA conformance, render
PDFs, shell out to Pandoc or PDF engines, or execute active PDF content.

## Evidence

- Rework-note check: current top-level `port-pandoc-*.needs-lane-rework.md`
  glob returned no files.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1305 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1321 assertions, 0 failures`.
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
- `PdfEngineHandoffTest.php` focused coverage increases by `+16` assertions.
- `lane-status.json` `phpPass`: `2418 -> 2419`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2807 -> 2808`.
- PDF engine inventory: `pdfEngineHandoffCoreCases` and
  `mappedPdfEngineHandoffCoreCases` move `12 -> 13`;
  `pdfEngineHandoffCoreAssertions` moves `108 -> 124`.

## Non-Overlap

This does not repeat earlier PDF-engine handoff slices for PDF engine argv,
template/header/resource planning, log/sidecar diagnostics, generated output
metrics, multipass reruns, bibliography sidecars, xref/object streams, page
metadata, outlines, document-info/XMP/PDF-A/PDF-UA metadata, output intents,
URI base, catalog requirements, LegalAttestation, DSS, signatures, AcroForm,
active actions, optional content, annotations, embedded files, marked-content
properties, ParentTree mappings, structure element accessibility metadata,
structure attributes, user properties, ClassMap extraction, ClassMap usage, or
IDTree policy. The new surface is the per-structure-element `/S` role chain
through `/StructTreeRoot /RoleMap`.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP
`PdfEngineHandoff` object parser, tagging metadata parser, structure element
parser, fake-runner result wiring, focused PHP test runner, and WordPress PDF
review-packet example. Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst,
browser renderers, roff, JavaScript execution, external PDF validators, online
services, live provider tests, and live-service provider tests remain out of
scope for this lane slice.

## Follow-Up

Next PDF-engine work should choose a non-overlapping fake-produced PDF
structure gap such as deeper RoleMap/ClassMap interaction, structure-tree
reference integrity beyond IDTree/ParentTree, or tagged-content marked-content
handoff policy while preserving the same no-engine fake-runner boundary.
