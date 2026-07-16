# markerPDF xref Prev chain wrong current offset current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T001158Z`
Base: `a227a39fdb58a8f8657363accdb74b31ff4570a6`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and metadata through parser-backed pdftext/PDFium object loading before WordPress-facing conversion. Under the no-GPU markerPDF scope, the native PHP lane owns the equivalent xref `/Prev` chain object-selection boundary for page text, catalog/XMP/Info metadata, and EmbeddedFiles name trees without running OCR, models, Python workers, PDFium, or external PDF tools.

PDF incremental-update xref-stream rows get their object numbers from `/Index`; the offset field must identify that row object's body. A damaged current row can point at a different current object offset. That is distinct from a malformed `/Index` row where direct-offset ownership is the only safe way to recover object numbers.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now treat a current offset owner as already valid only when it matches the xref row object number and generation. If the row object/generation has a current direct definition between `/Prev` and the latest xref offset, that row object wins before the wrong current offset owner. If no current definition exists for the row object, the existing malformed-`/Index` offset-owner recovery still applies.

The focused fixture keeps a correct `/Index [1 8 10 2]` but makes object `1`'s current xref-stream row point at the current object `2` offset. The old owner-short-circuit would leave object `1` to the stale `/Prev` catalog. The repaired path selects current catalog language, XMP/Info metadata, page text, and EmbeddedFiles attachment while excluding stale previous-section data.

## Evidence

Focused xref Prev-chain gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
9 PASS cases
1 test files, 139 assertions, 0 failures
```

Adjacent xref/parser repair family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevOffsetRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefIncrementalFreeEntryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridPrevTrailerSizeRepairCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 232 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
wrong_current_offset_row_object_current_info_selected=true
wrong_current_offset_row_object_current_language_selected=true
wrong_current_offset_row_object_current_text_selected=true
wrong_current_offset_row_object_current_attachment_selected=true
wrong_current_offset_stale_prev_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted indirect `/Prev` numeric helpers, xref-stream indirect `/Prev` object-stream helpers, malformed `/Index` direct-offset recovery, stale explicit previous offsets, zero-offset current row repair, classic xref-table damaged offset repair, hybrid `/Prev` trailer-size repair, xref free-entry suppression, object-stream carrier generation recovery, stream-filter operand owner boundaries, or live OCR/model work.

The bounded new behavior is specifically a correct current xref-stream `/Index` row whose explicit offset points at a different current object while the intended row object also has a current direct definition.

## Dependency Closure

No new support component is needed. This reuses native PHP direct-object scanning, xref-stream decoding, `/Prev` chain walking, current-update row repair, page-tree text extraction, XMP/Info/catalog metadata extraction, EmbeddedFiles name-tree extraction, and the existing WordPress smoke renderer. Full upstream parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
