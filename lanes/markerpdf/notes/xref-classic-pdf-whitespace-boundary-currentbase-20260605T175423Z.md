# markerPDF classic xref PDF-whitespace boundary current base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T175423Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T175423Z`

Base accepted HEAD: `2228852f98ddb548a846a90264176f0119562183`

## Source truth

The PDF lexical whitespace set includes NUL bytes. Native classic xref rebuild therefore must treat NUL as token-delimiting whitespace around the `xref` keyword, subsection headers, xref rows, and the `trailer` dictionary boundary. This is a native parser dependency for searchable-PDF WordPress imports; it is not OCR/model work.

## Implementation

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now accept PDF NUL whitespace in classic xref table rebuild parsing without admitting name-token or composite-token decoys. Xref rows normalize NUL delimiters before row parsing. The embedded-file and attachment xref scans also fail closed when a damaged unterminated literal begins before later `xref`/`trailer` bytes, preserving the existing string-decoy boundary.

`PdfAttachmentExtractor` now reuses the latest top-level trailer fallback for catalog-root selection when no valid top-level `startxref` remains, so attachment summaries stay rooted to the current catalog and do not enumerate later decoy catalogs hidden inside damaged literals.

## Evidence

Red-first focused failure before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicPdfWhitespaceBoundaryCurrentBaseTest.php
FAIL accepts PDF NUL whitespace in rebuilt classic xref tables before WordPress imports
Expected current text lines; actual stale text lines.
1 test files, 3 assertions, 1 failures
```

Focused pass after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicPdfWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicUnterminatedLiteralBoundaryCurrentBaseTest.php
PASS accepts PDF NUL whitespace in rebuilt classic xref tables before WordPress imports
PASS skips unterminated literal-string xref decoys before WordPress imports
2 test files, 58 assertions, 0 failures
```

Adjacent classic-xref family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicPdfWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicCommentDelimiterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicSignedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicGenerationOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicUnterminatedLiteralBoundaryCurrentBaseTest.php
8 test files, 840 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-pdf-whitespace-currentbase.php
```

Emits `pdf_nul_whitespace_accepted=true`, `current_classic_xref_import_kept=true`, `stale_prev_import_excluded=true`, `metadata_title=Current PDF-Whitespace XRef Title`, `embedded_file=current-pdf-whitespace-xref.xml`, `attachment_filenames=["current-pdf-whitespace-xref.xml"]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, plus two current Gutenberg paragraph comments.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted comment-delimiter xref keywords, comment-only xref rows, commented `startxref` tokens, malformed or signed `startxref` operands, stale numeric startxref repair, zero-count sections, malformed or punctuation rows, trailing malformed subsections, name/composite/literal/stream-owned xref decoys, stream-owned trailers, post-startxref trailers, linearized hint ranges, generation-offset repair, forward `/Prev` repair, xref-stream `/Prev`, object-stream, hybrid, encryption, metadata, attachment generation, font/CMap, image/filter, annotation/form, OCR, or supplied-boundary table/equation work. The bounded behavior is only NUL as PDF lexical whitespace in rebuilt classic xref tables and the attachment-root fallback needed to preserve the adjacent unterminated-literal boundary.

## Dependency closure

No new support component is needed. This reuses the native PHP direct-object scanner, PDF keyword-boundary checks, classic xref table/trailer parser, page-tree walker, metadata extractor, EmbeddedFiles extractor, attachment preflight, stream decoder, text-token extractor, and WordPress smoke path. Live OCR, PDFium rendering, Surya/Torch/Texify models, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
