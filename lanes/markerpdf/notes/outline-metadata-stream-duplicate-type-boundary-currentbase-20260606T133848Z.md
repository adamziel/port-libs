# markerpdf-outline-metadata-boundary-current-base-20260606T133848Z

## Scope

- Lane: `markerpdf`
- Behavior cluster: native no-GPU PDF outline item `/Metadata` stream dictionary role boundary.
- Accepted base: `1f5e5a83d969498573a313dba1838afefd977f4f`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` receives searchable PDF text, metadata, and outline/navigation information at the PDF parser boundary before model/OCR stages.
- PDF metadata XML streams are identified by stream dictionary role keys `/Type /Metadata` and `/Subtype /XML`.
- Duplicate `/Type` or `/Subtype` keys make the stream role ambiguous. The native PHP catalog metadata path already fails closed with `rejected_duplicate_metadata_stream_type_keys`; this slice applies that same trust boundary to bookmark-local outline item `/Metadata` stream review.

## Implementation

- `PdfMetadataExtractor::documentOutlineItemMetadataStreamReview()` now checks `metadataStreamDictionaryTypeBoundaryReview()` after stream object boundary validation and before generic non-metadata stream rejection.
- Duplicate `/Type` and `/Subtype` outline metadata streams now record:
  - `status=rejected_duplicate_metadata_stream_type_keys`
  - duplicate key names, entry counts, and decoded type/subtype values
  - decoded byte count, SHA-256, filters, declared length, and redacted XMP summary
- The outline row remains importable for TOC/navigation, but bookmark-local XMP text is not promoted to document XMP, navigation text, or visible WordPress body text.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamDuplicateTypeBoundaryCurrentBaseTest.php
```

Before the source change:

```text
FAIL rejects outline Metadata streams with duplicate Type and Subtype dictionary keys
Expected: 'rejected_duplicate_metadata_stream_type_keys'
Actual: 'rejected_non_metadata_outline_item_stream'
1 test files, 23 assertions, 1 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamDuplicateTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
2 PASS
1 test files, 45 assertions, 0 failures
```

Adjacent outline metadata family plus catalog duplicate-Type XMP guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpStreamDictionaryDuplicateTypeBoundaryCurrentBaseTest.php
Focused test run: 46 selected test files (root lock skipped)
92 PASS
46 test files, 1866 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-duplicate-type-boundary-currentbase.php
```

Emits `metadata_status=rejected_duplicate_metadata_stream_type_keys`, `metadata_duplicate_keys=["Type","Subtype"]`, `metadata_payload_included=false`, `metadata_accepted_as_document_xmp=false`, `visible_text_excludes_outline_metadata_payload=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted catalog XMP duplicate Type/Subtype rejection, outline duplicate top-level `/Metadata` key selection, malformed outline metadata operands, non-stream outline metadata references, malformed stream tails, object-stream outline metadata, generation boundaries, root/item traversal boundaries, action-chain review, name-tree destination review, Type3 CharProcs glyph boundaries, xref repair, attachments, forms, annotations, page geometry, stream filters, OCR, or model execution. The bounded behavior is specifically selected outline item `/Metadata` stream dictionaries whose own stream role keys contain duplicate `/Type` and `/Subtype` entries.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, stream decoder, catalog metadata duplicate-key boundary helper, outline item metadata stream review, XMP packet summary redaction, TOC/navigation extractor, and WordPress smoke path. Live OCR, PDFium rendering, Surya/Texify/Torch model execution, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
