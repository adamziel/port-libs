# Pandoc PDF Engine Handoff Outline Tree 2026-06-05

## Scope

Micro-slice:
`pandoc-pdf-engine-handoff-core-current-base-20260605T162532Z`.

Accepted base:
`0585b2b7da1a961ebb2bf4a159a9d8aa9a676470`.

This slice stays inside the bounded native PDF-output fake-runner handoff. It
does not implement or invoke Pandoc, TeX/PDF engines, Typst, browser renderers,
roff renderers, external PDF validators, JavaScript, online services, Cabal, or
Haskell runners.

## Implemented Behavior

`PdfEngineHandoff` now inspects fake-runner produced PDF bytes for bounded
outline tree item metadata:

- Catalog `/Outlines` root traversal through `/First` and `/Next`, with bounded
  child traversal through outline item `/First` references.
- Existing `pdfOutlineTitles` remains unchanged for callers that only need the
  title list.
- New `pdfOutlines` and `finalPdfOutlines` payloads expose outline object
  references, decoded titles, parent/prev/next/first/last references, `/Count`,
  open/closed state, direct or indirect destination page/fit metadata, and
  simple URI/Launch/Named/GoTo action targets.
- Fake-runner diagnostics now include outline metadata, open/closed,
  destination, and action counts.
- The WordPress PDF handoff example now exposes outline item metadata in first
  and final fake-run summaries.

## Source Truth And Non-Overlap

The source-truth boundary is the accepted static Pandoc inventory plus the
`pandoc-pdf-engine-handoff-core` contract: Pandoc writes/intermediates and
delegates PDF production to engines, while this lane records the produced-byte
handoff diagnostics without executing those engines.

This does not repeat prior PDF handoff coverage for engine argv planning,
template/header/resource handoff, expected sidecars, source/resource hashing,
engine logs, bibliography sidecars, SyncTeX/FLS/transcript graphs, output
metrics, trailer/xref/object streams, page geometry/labels/timings, fonts,
images, Form XObjects, document info, XMP/PDF-A, output intents, catalog
viewer preferences, named destinations, tagging/structure elements,
annotations, embedded files, AcroForm fields, digital signatures, active
actions, optional content, or encryption preflight.

## Verification

Baseline focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: `1 test files, 467 assertions, 0 failures`.

Red-first check after adding the outline metadata expectation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: `1 test files, 469 assertions, 1 failures`.
- Failure: missing `pdfOutlines` fake-runner result field.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: `1 test files, 476 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `lane-status.json` `phpPass`: `993` to `994`.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `1448` to `1449`.
- PDF engine handoff core cases: `10` to `11`.
- PDF engine handoff core assertions: `95` to `104`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`PdfEngineHandoff` object, dictionary, array, reference, string, and
destination parsing helpers.

Full upstream runner closure remains gated on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` plus non-mutating Cabal planning for
`test:test-pandoc` and `test:test-pandoc-lua-engine`.

## Follow-Up

Keep remote GoToR destinations, richer outline action dictionaries, outline
style/color flags, marked-content to outline validation, PDF collection/
portfolio metadata, stream decompression, xref repair, renderer execution, and
PDF/UA/PDF-A conformance validation as separate bounded slices.
