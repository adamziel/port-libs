# Pandoc PDF Engine Handoff: AcroForm Calculation Order

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T102350Z`
Base accepted HEAD: `a54545a529de1862e6e524e6822e40ce7f7c6600`
Date: 2026-06-08 UTC

## Scope

This slice adds bounded native PHP review metadata for produced-PDF AcroForm
calculation order. The fake PDF runner now resolves `/AcroForm` `/CO` field
references into a `pdfAcroFormCalculationOrder` list with field object,
resolved field name, field type, type label, alternate name, mapping name,
flags, flag names, and missing-reference markers. The same metadata is exposed
in `finalPdfAcroFormCalculationOrder` for sequence handoffs.

The behavior is a handoff-only support-library mapping. It does not execute PDF
form calculations, JavaScript, Pandoc, TeX/PDF engines, Typst, browser
renderers, roff, external PDF validators, online services, live provider tests,
or live-service provider tests.

## Evidence

- Baseline focused check before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 839 assertions, 0 failures`.
- Red-first probe after adding the fixture failed as expected with
  `1 test files, 841 assertions, 1 failures` because
  `pdfAcroFormCalculationOrder` was absent.
- Final focused check:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 846 assertions, 0 failures`.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1616` to `1617`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2035` to
  `2036`.
- PDF engine handoff inventory: `pdfEngineHandoffCoreCases` and
  `mappedPdfEngineHandoffCoreCases` `12` to `13`.
- PDF engine focused assertion inventory: `108` to `115`.

## Dependency Closure

No new support component is needed. The implementation reuses the existing
`PdfEngineHandoff` PDF object parser, AcroForm dictionary traversal, field
metadata summarizer, fake-runner diagnostics, sequence summary, and WordPress
PDF handoff example.

## Non-Overlap

This does not overlap prior PDF fake-runner coverage for raw AcroForm
metadata, outlines, annotations, signatures, embedded files, viewer
preferences, optional content, collection metadata, page geometry, images,
fonts, CMaps, encryption preflight, XMP/Info metadata, tagging, marked content,
or active action summaries. It only resolves the already surfaced `/CO`
calculation-order references into reviewer-visible field metadata and missing
reference diagnostics.

## Follow-Up

A useful next PDF engine handoff slice would stay in produced-PDF review
metadata and add a non-overlapping native mapping such as AcroForm action
target-list flags, default appearance/default resource resolution, or another
bounded form-review diagnostic that does not require external PDF engines or
script execution.
