# Outline Metadata Role Operand Boundary Current Base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260608T182138Z`

Base: `74e2e1d508ba035b714146936835879271d84645`

## Source Truth

The pinned markerPDF manifest records upstream `sddai/markerPDF` at
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the current no-GPU lane
scope, outline/bookmark rows and document metadata are native parser handoffs
before OCR/layout/model execution. PDF metadata stream role entries such as
`/Type` and `/Subtype` are single-value trust boundaries: a bookmark-local
`/Metadata` stream whose role operand has hidden extra top-level tokens must
stay review-only, not become document XMP or visible WordPress text.

## Behavior

- `PdfMetadataExtractor::documentOutlineItemMetadataStreamReview()` now maps
  tailed `/Type` or `/Subtype` metadata-stream role operands into
  outline-local statuses:
  - `rejected_tailed_outline_item_metadata_stream_role_operand`
  - `rejected_tailed_outline_root_metadata_stream_role_operand`
- The underlying catalog XMP role-operand behavior remains unchanged as
  `rejected_tailed_metadata_stream_role_operand`.
- Outline root and item review rows now expose
  `role_operand_boundary=single_name_token`,
  `role_operand_boundary_rejected=true`, and
  `role_operand_policy=reject_tailed_outline_metadata_stream_role_operands`.
- Rejected outline-local XMP bytes are summarized with hashes and redacted XMP
  field names only; payload text is excluded from `document_outline`,
  navigation review, TOC rows, and WordPress paragraph text.

## Red-First Evidence

Before the source patch, the focused test failed because the outline root
metadata stream was rejected with the generic catalog-style status:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRoleOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed outline Metadata stream role operands without promoting XMP payloads
Expected: 'rejected_tailed_outline_root_metadata_stream_role_operand'
Actual: 'rejected_tailed_metadata_stream_role_operand'
1 test files, 10 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRoleOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed outline Metadata stream role operands without promoting XMP payloads
1 test files, 65 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpRoleOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamDuplicateTypeBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
PASS rejects indirect tailed metadata stream Subtype name helpers before document XMP promotion
PASS rejects direct tailed metadata stream Type names before document XMP promotion
PASS rejects outline Metadata streams with duplicate Type and Subtype dictionary keys
PASS keeps duplicate Type outline Metadata payloads out of TOC navigation and visible WordPress text
PASS rejects non-metadata and malformed outline Metadata streams as review-only boundary rows
PASS keeps rejected outline Metadata stream payloads out of TOC navigation and visible WordPress text
3 test files, 145 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-role-operand-boundary-currentbase.php
Result: exits 0 with root_review_status="rejected_tailed_outline_root_metadata_stream_role_operand",
item_review_status="rejected_tailed_outline_item_metadata_stream_role_operand",
visible_text_excludes_outline_role_payloads=true,
navigation_excludes_outline_role_payloads=true,
executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary
parser, metadata stream decoder/reviewer, outline metadata extractor,
navigation review handoff, and WordPress smoke output. OCR, Surya/Texify/Torch,
pypdfium/PDFium execution, raster rendering, Streamlit/FastAPI workers, and
external PDF tools remain intentionally out of scope for this no-GPU markerPDF
lane.

## Non-Overlap

This does not repeat accepted catalog XMP role-operand fallback, outline
metadata stream duplicate `/Type` or `/Subtype` keys, non-metadata stream
rejection, malformed stream-tail rejection, malformed `/Metadata` reference
operands, selected-null `/Metadata`, outline action/destination operands,
structure-element `/SE` boundaries, xref repair, image/filter metadata, forms,
annotations, table/equation supplied boundaries, or OCR/model execution. The
bounded behavior is only outline-root and outline-item metadata stream
role-operand provenance and fail-closed review labels.

## Next Task

Continue with non-overlapping native markerPDF parser behavior around fonts,
CMaps, xref repair, page geometry, annotations/forms, image/filter metadata,
and supplied-boundary table/equation handoffs.
