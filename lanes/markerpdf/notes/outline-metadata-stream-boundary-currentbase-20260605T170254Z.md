# Outline Metadata Stream Boundary

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T170254Z`

Accepted base: `3e922ffb90f045be92470ed06339fb276388af76`

## Source Truth

Upstream markerPDF treats document outlines/bookmarks as navigation and review metadata, not as visible page text. PDF outline item dictionaries may carry a `/Metadata` entry; that stream is owned by the bookmark item and must be inspected as payload-free review metadata rather than promoted to document-level XMP or emitted into WordPress paragraphs during lightweight stream fallback extraction.

## Red-First Boundary

A page-tree-free searchable-PDF fallback fixture contains:

- a catalog `/Outlines` tree with one outline item;
- the outline item `/Metadata 8 0 R` stream using `/Type /Metadata`, `/Subtype /XML`, and `/Filter /FlateDecode`;
- metadata stream bytes containing PDF text operators that previously leaked through the stream-only fallback path;
- a separate visible content stream.

Before the patch, `PdfTextExtractor::extractPlainText()` returned the visible content plus `Outline item metadata stream leaked into body`, and `PdfMetadataExtractor` did not expose a payload-free outline metadata-stream review row.

## Implementation

`PdfMetadataExtractor` now adds `metadata_stream_review` on outline item rows for top-level `/Metadata` indirect stream references. The review includes source/status flags, decoded byte count, SHA-256, type/subtype, filters, declared length when available, and optional XMP summary without including stream payload bytes or accepting the bookmark-local stream as document XMP.

`PdfTextExtractor` now detects exact-generation streams referenced by outline-item `/Metadata` dictionaries and excludes those streams from the decoded-stream fallback path. The exclusion is scoped to dictionaries that look like outline items and keeps ordinary visible content streams eligible for extraction.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamBoundaryCurrentBaseTest.php`  
  `1 test files / 28 assertions / 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-boundary-currentbase.php`  
  emits `metadata_payload_excluded_from_document_metadata=true`, `metadata_payload_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PDF object resolver, stream decoder/filter stack, outline metadata walker, and fallback text stream boundary logic. GPU/model OCR, Surya/Texify/Torch, PDFium, Python, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
