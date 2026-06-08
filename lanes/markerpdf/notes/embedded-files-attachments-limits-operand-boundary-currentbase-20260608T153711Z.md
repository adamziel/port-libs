# markerPDF EmbeddedFiles Attachment Limits Operand Boundary

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T153711Z`
Base accepted HEAD: `f922b306fce650315c26b7148db82f2371b8d024`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF parsing before OCR/model stages. Embedded-file payload bytes are attachment metadata, not page text.
- PDF name-tree `/Limits` values are arrays. If a producer stores `/Limits` in an indirect object, that object must resolve to one top-level array; trailing top-level operands after the array are malformed and must not influence attachment boundaries.
- This stays inside native no-GPU markerPDF scope: EmbeddedFiles/FileSpec review, no OCR, no PDFium rendering, no model execution, no decryption, and no external PDF tools.

## Implementation

- `PdfAttachmentExtractor` now rejects an EmbeddedFiles name-tree node when the node's `/Limits` operand does not resolve to exactly one top-level array object.
- `PdfEmbeddedFileExtractor` now applies the same exact-array `/Limits` guard and uses exact array resolution when reading name-tree limits.
- Added `PdfAttachmentIndirectNameTreeLimitsOperandBoundaryCurrentBaseTest.php`, where a malformed child node points `/Limits` at `[(tailed-limits.xml) (tailed-limits.xml)] 30 0 R`.
- Added `wordpress-pdf-attachment-limits-operand-boundary-currentbase.php`, proving the valid sibling attachment remains available for WordPress review while the malformed node and tail decoy are excluded.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectNameTreeLimitsOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed indirect EmbeddedFiles Limits arrays before WordPress attachment review
Values are not identical
Expected: 1
Actual: 2

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentIndirectNameTreeLimitsOperandBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachment-limits-operand-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentIndirectNameTreeLimitsOperandBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachment-limits-operand-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectNameTreeLimitsOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed indirect EmbeddedFiles Limits arrays before WordPress attachment review

1 test files, 63 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectNameTreeLimitsOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentIndirectNameTreeArrayOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentNameTreeKidLimitsOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentNameTreePdfDocEncodingByteLimitsCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 242 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*Test.php lanes/markerpdf/tests/PdfEmbeddedFile*Test.php lanes/markerpdf/tests/PdfEmbeddedFiles*Test.php
Focused test run: 69 selected test files (root lock skipped)
69 test files, 5049 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-limits-operand-boundary-currentbase.php
```

The smoke exits 0 and emits `attachment_count=1`, `embedded_file_count=1`, `valid_sibling_preserved=true`, `tailed_limits_node_rejected=true`, `tail_operand_decoy_excluded=true`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- PHP behavior tests: `3243 -> 3244`.
- WordPress scenarios: `2650 -> 2651`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, token-aware dictionary parser, exact array operand resolver, EmbeddedFiles name-tree traversal, FileSpec parser, stream decoder, checksum review, attachment summary sanitizer, and visible text boundary. GPU/model/OCR/PDFium parity remains intentionally out of scope for this markerPDF lane.

## Non-Overlap

This does not repeat accepted indirect `/Names` array rejection, indirect `/Kids` array rejection, direct name-tree node trailing operand rejection, `/Names` and `/Kids` mixed-node handling, child `/Kids /Limits` sorting, PDFDocEncoding byte-limit comparison, duplicate name-tree keys, direct/indirect FileSpec duplicate-key rejection, catalog/page/annotation/StructElem `/AF` extraction, stream filter/DecodeParms boundaries, encrypted `/EFF` redaction, object-stream/xref attachment selection, metadata, fonts, CMaps, image/filter review, OCR, or supplied table/equation handoffs. The bounded behavior is only rejecting tailed direct or indirect EmbeddedFiles `/Limits` array operands before a name-tree node contributes attachment rows.
