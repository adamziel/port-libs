# markerPDF Direct Outline-Root Metadata Stream Boundary

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260608T170649Z`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream receives searchable-PDF text and outline/navigation metadata from the PDF parser boundary before OCR/model handoff. In the no-GPU PHP lane, outline `/Metadata` streams are review-only bookmark/navigation payloads, not visible WordPress text.
- PDF catalogs may embed the `/Outlines` root as a direct dictionary. If that direct outline root carries `/Metadata`, the stream must be reviewed like an outline-root metadata stream and excluded from fallback stream text even though the root has no indirect object number.

## Red-First Evidence

Before the source edit, this focused probe leaked the direct root metadata payload into fallback text:

```text
php -r 'require "tools/bootstrap.php"; ...; var_export((new PortLibs\MarkerPDF\PdfTextExtractor())->extractPlainText($pdf));'
'Direct root metadata payload leak
Direct root metadata visible body'
```

After the source edit, the same probe returned only:

```text
'Direct root metadata visible body'
```

## Implementation

- `PdfTextExtractor::outlineMetadataObjectGenerationSet()` now reuses a shared metadata-reference collector for outline item/root dictionaries.
- The same scan now inspects catalog objects for direct `/Outlines << ... >>` root dictionaries and marks their `/Metadata` stream object generations for fallback exclusion.
- Document metadata behavior is preserved: `PdfMetadataExtractor` still reports direct root `/Metadata` under `document_outline.metadata_stream_review` with `source=outline_root_metadata_stream`, `accepted_as_document_xmp=false`, stream hash, and redacted XMP summary.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootMetadataStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records direct catalog outline-root Metadata as review-only document outline metadata
PASS excludes direct outline-root Metadata stream bytes from fallback WordPress text

1 test files, 39 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfOutlineMetadata.*Test\.php$' | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 76 selected test files (root lock skipped)
...
76 test files, 3845 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-direct-root-metadata-currentbase.php
```

Passed. The smoke emits `metadata_stream_status=reviewed_outline_root_metadata_stream`, `metadata_stream_object=8`, `metadata_stream_accepted_as_document_xmp=false`, `metadata_payload_excluded_from_document_metadata=true`, `metadata_payload_excluded_from_lightweight_metadata=true`, `metadata_payload_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct outline-root traversal, outline root `/Metadata` review in `PdfMetadataExtractor`, root metadata navigation propagation, titleless outline item metadata fallback exclusion, duplicate outline metadata selection, tailed metadata operand rejection, unreadable metadata stream tail rejection, outline destination/action review, xref/trailer-root selection, page labels, annotations, forms, images, CMaps, OCR, models, or external PDF tool execution. The bounded behavior is only fallback stream exclusion for `/Metadata` streams referenced by a direct catalog `/Outlines` root dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, direct catalog outline-root parser, outline metadata review extractor, stream decoder, text fallback scanner, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI workers, JavaScript/PDF action execution, decryption/password validation, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
