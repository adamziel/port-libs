# markerpdf-outline-metadata-boundary-current-base-20260606T193910Z

## Scope

- Lane: markerpdf
- Micro-slice: markerpdf-outline-metadata-boundary-current-base-20260606T193910Z
- Accepted base: 29e4f7bdda7c79644e6c2fd45009285d82e10a2f
- Behavior cluster: native no-GPU PDF outline root `/Metadata` stream review and fallback-text exclusion.

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` receives document metadata and outline/navigation data through PDF parser layers before any OCR/model fallback. In this native PHP lane, `/Outlines` remains review-only document/navigation metadata, and metadata streams attached to the outline root must not become document-root XMP fields or visible WordPress paragraph text.

## Implementation

- `PdfMetadataExtractor::documentOutlineMetadata()` now records a payload-free `metadata_stream_review` for outline-root `/Metadata` operands.
- The review reuses the existing outline item metadata-stream trust boundary, with root-specific `outline_root_metadata_stream` source and `reviewed_outline_root_metadata_stream` status labels.
- `PdfTextExtractor::outlineMetadataObjectGenerationSet()` now also recognizes outline root dictionaries, so exact-generation root `/Metadata` streams are skipped by lightweight decoded-stream fallback text extraction.

## Red First

Before the source fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php
```

Result: `1 test files, 21 assertions, 2 failures`.

- `document_outline.metadata_stream_review.source` was `NULL` instead of `outline_root_metadata_stream`.
- Lightweight fallback text included `Outline root metadata fallback payload must stay hidden`.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php
```

Result: `1 test files, 41 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataNavigationReviewCurrentBaseTest.php
```

Result: `5 test files, 267 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-root-metadata-stream-currentbase.php
```

Result: emits `metadata_stream_status=reviewed_outline_root_metadata_stream`, `accepted_as_document_xmp=false`, `metadata_payload_excluded_from_document_metadata=true`, `metadata_payload_excluded_from_navigation_metadata=true`, `metadata_payload_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted outline item `/Metadata` stream review, duplicate `/Metadata` item keys, malformed item metadata operands, item stream type/duplicate-type checks, outline root stream rejection, root count/last/prev/parent traversal boundaries, xref/trailer root selection, named destinations, page labels, action-chain review, annotations, forms, images, fonts, CMaps, stream filters, or supplied table/equation behavior. The bounded behavior is only outline-root `/Metadata` stream review and fallback exclusion.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, stream decoder, outline metadata summarizer, and text fallback stream exclusion. GPU/OCR/model execution, PDFium/PIL rendering, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope.
