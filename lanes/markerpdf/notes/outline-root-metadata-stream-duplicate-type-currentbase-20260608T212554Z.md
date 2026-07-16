# markerpdf-outline-metadata-boundary-current-base-20260608T212554Z

## Scope

- Lane: `markerpdf`
- Behavior cluster: native no-GPU PDF outline root `/Metadata` stream dictionary role boundary.
- Accepted base: `d1134e2a181aaf4c0c02f2b0d3b93f388be55ad8`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` receives searchable PDF text, document metadata, and outline/navigation data from PDF parser dependencies before OCR/model stages.
- PDF metadata XML streams are identified by stream dictionary role keys `/Type /Metadata` and `/Subtype /XML`.
- Duplicate `/Type` or `/Subtype` keys make that stream role ambiguous. This slice applies the existing fail-closed metadata stream role boundary to outline root `/Metadata` review metadata and records that root-local streams are not document XMP.

## Implementation

- `PdfMetadataExtractor::documentOutlineRootMetadataStreamReview()` now adds root-local scope fields to outline root metadata stream reviews:
  - `outline_metadata_scope=root`
  - `outline_metadata_scope_object=Outlines`
  - `document_xmp_promotion_boundary=outline_root_review_only`
  - `root_metadata_stream_local_to_outline=true`
- `documentOutlineRootMetadataStreamSummary()` mirrors those fields to `document_outline` summary keys for importer review UIs.
- Added a fixture where `/Outlines /Metadata` points at a stream with duplicate `/Type` and `/Subtype` role keys. The stream remains review-only with duplicate-key, type/subtype, length, filter, byte-count, SHA-256, and redacted XMP summary evidence.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRootMetadataStreamDuplicateTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
2 PASS
1 test files, 56 assertions, 0 failures
```

Adjacent outline metadata stream/root regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootSummaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamDuplicateTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamLengthOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRoleOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRootMetadataNavigationBoundaryCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
17 PASS
8 test files, 492 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-root-metadata-stream-duplicate-type-currentbase.php
```

Emits `metadata_scope=root`, `metadata_status=rejected_duplicate_metadata_stream_type_keys`, `metadata_payload_included=false`, `metadata_accepted_as_document_xmp=false`, `visible_text_excludes_outline_root_metadata_payload=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted outline item duplicate `/Type` and `/Subtype` stream role rejection, catalog XMP duplicate Type/Subtype rejection, outline duplicate top-level `/Metadata` key selection, selected null metadata entries, malformed outline metadata operands, stream length/filter/decodeparms operand boundaries, root/item traversal boundaries, xref repair, attachments, forms, annotations, page geometry, OCR, or model execution. The bounded behavior is specifically outline root `/Metadata` streams whose own stream dictionary role keys are ambiguous.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, stream decoder, metadata stream dictionary role-boundary helper, outline root metadata review, XMP packet summary redaction, TOC/navigation extractor, and WordPress smoke path. Live OCR, PDFium rendering, Surya/Texify/Torch model execution, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
