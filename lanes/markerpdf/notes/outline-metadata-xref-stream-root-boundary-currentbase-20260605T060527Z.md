# Outline Metadata XRef Stream Root Boundary - 2026-06-05

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T060527Z`

Base accepted HEAD: `201994e3e21153568b63e72469dc7c6770ad6028`

## Source truth

- Upstream Marker exposes document conversion as markdown/JSON/HTML and includes links/references in the conversion surface. This native PHP lane keeps the searchable-PDF, no-model boundary aligned with that metadata path without launching OCR, Surya, Texify, Torch, or model workers.
- PDF 1.5+ cross-reference streams store trailer entries in the `/Type /XRef` stream dictionary. A current xref-stream `/Root` must select the current document catalog before stale earlier catalogs are considered, the same way the existing classic xref-table trailer-root boundary already works.

## Behavior

`PdfOutlineExtractor` now follows the latest `startxref` offset through either a classic `xref` trailer or an xref stream dictionary. When the latest xref stream supplies `/Root 20 0 R`, outline extraction, navigation review metadata, and document-outline metadata reorder catalog candidates around object `20` before falling back to first-in-file catalog order.

The focused fixture keeps a stale catalog/object tree at object `1` with a stale outline title and JavaScript action, then writes the current catalog at object `20` and a final Flate-compressed xref stream at object `90` with `/Root 20 0 R`. The test verifies:

- document metadata reports outline root object `25` and first item `26`;
- TOC/navigation rows contain only `Current XRef Stream Root Outline`;
- destination view metadata resolves `/FitH 640`;
- outline color review resolves `#0066cc`;
- stale outline title/action metadata never leaks into review JSON;
- visible WordPress text is the current page body, not outline metadata or stale page text.

## Evidence

Initial red-first probe before the implementation showed `PdfMetadataExtractor` selecting `Current XRef Stream Root Outline` while `PdfOutlineExtractor` TOC/navigation still selected `Stale XRef Stream Root Outline`. After the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataXrefStreamRootBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses current xref-stream trailer Root for outline document metadata and navigation
PASS keeps stale xref-stream root outline metadata out of visible WordPress text

1 test files, 23 assertions, 0 failures
```

## Dependency closure

No new support component is needed. This reuses the existing native PDF token/dictionary parser and current xref-stream decoding already used by text/metadata extraction. The remaining excluded upstream parity is the current no-GPU scope: scanned-PDF OCR, Surya/Texify/Torch model execution, and exact model benchmark parity.

## Non-overlap

This does not repeat the accepted classic trailer-root boundary, outline EOF boundary, xref owner/generation repairs, pdftext Reference anchor synthesis, or model/OCR paths. It is specifically the missing outline/metadata catalog-root boundary when the latest `startxref` points at a cross-reference stream object.
