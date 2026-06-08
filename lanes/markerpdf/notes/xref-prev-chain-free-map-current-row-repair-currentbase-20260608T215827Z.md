# markerPDF xref Prev chain free-map current-row repair current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T215827Z`
Base: `061aea1caf3c0acd567538f40de503a885da8ad4`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF object selection to pdftext/PDFium-backed parsing before annotation/link review. The native no-GPU PHP lane owns the equivalent xref `/Prev` chain boundary for WordPress import.

Incremental PDFs can carry a valid current object body while the latest xref subsection is malformed: a current in-use row may be numbered as one object but point at the byte offset of another direct object in the current update window. The full extraction paths already repair current-row offset owners before falling back to `/Prev`; the lightweight `PdfXrefFreeObjectMap` used by link/annotation preflight must do the same before inherited free rows suppress current annotation objects.

## Change

- `PdfXrefFreeObjectMap::xrefEntriesFromOffsetChain()` now repairs latest in-use entries by direct-object offset owner when the pointed object is inside the current update window before merging inherited `/Prev` rows.
- Added a focused fixture where the previous xref table marks annotation object `7` free, while the latest xref table has a malformed in-use row numbered `30` that points at current object `7`.
- Added a WordPress smoke proving current link annotation promotion survives while stale previous annotation review text stays excluded without PDF action execution, Python/models, OCR, or external PDF tools.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeMapCurrentRowRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs latest Prev-chain free-object map rows by current offset owner before link review (lanes/markerpdf/tests/PdfXrefPrevChainFreeMapCurrentRowRepairCurrentBaseTest.php)
The malformed latest row owns current object 7 before inherited free rows merge.

1 test files, 1 assertions, 1 failures
```

Focused passing run after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeMapCurrentRowRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs latest Prev-chain free-object map rows by current offset owner before link review

1 test files, 17 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-free-map-current-row-repair-currentbase.php
```

The smoke reports `free_map_repaired=true`, `current_link_promoted=true`, `current_annotation_visible=true`, `stale_review_excluded=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat duplicate free-row suppression, damaged `/Prev` pointer repair, same-generation metadata row repair, object-stream carrier repair, xref-stream trailer metadata precedence, or the full text/metadata/attachment current-row repair paths. The bounded behavior here is the lightweight free-object map applying current direct-offset ownership before inherited `/Prev` free rows suppress WordPress link/annotation review.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF direct-object scanner, classic xref table parser, `/Prev` chain merger, annotation/link extractors, and WordPress smoke renderer. GPU/model/OCR, Surya/Texify/Torch, pypdfium/PDFium rendering, Python marker runtime, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
