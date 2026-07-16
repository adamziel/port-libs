# markerPDF annotation object-stream offset boundary current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260607T021652Z`
Session: `port-dev-markerpdf-object-xref-20260607T021652Z`
Accepted base: `067c20d4516457e7c630a9a0a09157a4c0c95111`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and annotation/link metadata through pdftext/PDFium-backed parsing before Marker applies downstream conversion. Under the current no-GPU markerPDF scope, this PHP lane owns native xref/object-stream parser boundaries that decide which annotation dictionaries are safe to promote into WordPress review metadata and link spans.

PDF xref-stream type-2 rows select generation-zero object-stream members by object-stream number and member index. The selected member offset is relative to `/First` and must start at a top-level PDF object token, not inside another member's literal string, comment, array, hex string, or dictionary.

## Behavior

Before this patch, `PdfAnnotationExtractor` and `PdfLinkAnnotationExtractor` sliced xref-selected object-stream annotation members at the declared offset without validating token ownership. A malformed object stream could point annotation object `7` into another member's literal string where a fake `<< /Type /Annot /Subtype /Link ... >>` dictionary appeared, causing WordPress annotation review and link promotion to expose the malicious URI.

The patch adds member-offset token-boundary validation to both lightweight annotation expanders. It also ignores malformed later offsets when determining a valid member's end boundary, matching the fail-closed behavior already used by the heavier text/metadata paths.

## Evidence

Red-first focused run after adding the test and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkObjectStreamReviewBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses xref-stream object-stream Link annotation bodies for annotation review before stale direct bodies
FAIL rejects annotation object-stream member offsets inside literal strings before WordPress link promotion (lanes/markerpdf/tests/PdfAnnotationLinkObjectStreamReviewBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 8,
)
Actual: array (
  0 => 8,
  1 => 7,
)
1 test files, 28 assertions, 1 failures
```

After source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkObjectStreamReviewBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses xref-stream object-stream Link annotation bodies for annotation review before stale direct bodies
PASS rejects annotation object-stream member offsets inside literal strings before WordPress link promotion
1 test files, 53 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-object-stream-offset-boundary-currentbase.php
```

The smoke reports `malformed_offset_annotation_excluded=true`, `stale_direct_annotation_excluded=true`, `annotation_payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

## Non-Overlap

This does not repeat accepted text, metadata, attachment, AcroForm, named-destination, or outline object-stream member-offset guards; explicit type-2 member-index selection; zero-width index recovery; duplicate object-number or duplicate-offset guards; header comment or plus-signed header parsing; `/First` boundary validation; object-stream filter-chain operands; xref `/Prev` carrier repair; or stream-owned xref/object owner boundaries.

The bounded behavior here is only lightweight page annotation and link annotation review rejecting xref-selected object-stream member offsets that point inside another member's literal string before WordPress link promotion.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF direct-object scanner, xref-stream parser, object-stream decoder, annotation extractor, link annotation extractor, Markdown postprocessor, and WordPress smoke path. GPU/model/OCR execution, Surya/Torch/Texify, PDFium/pypdfium runtime execution, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
