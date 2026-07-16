# Classic Xref Missing-Startxref EOF Boundary Current Base

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260606T115651Z`  
Base accepted HEAD: `9f43fcc1a47b08850d5cb210982f3f518404def8`

## Source Truth

- Upstream `sddai/markerPDF` uses `pdftext`/PDF parser output as the searchable-PDF text source before OCR/model stages. This PHP lane mirrors that native parser boundary for current page text, metadata, and attachment review without launching PDFium, OCR, Surya, Texify, Torch, Streamlit/FastAPI workers, or external PDF tools.
- Classic PDF xref recovery may scan for a usable xref table when `startxref` is missing or malformed, but recovery must remain bounded by the terminal PDF EOF marker. Post-EOF garbage must not replace the current catalog, Info/XMP metadata, page tree, or EmbeddedFiles name tree during WordPress import.

## Behavior

Missing-startxref classic xref rebuild fallback now stops at the terminal top-level `%%EOF` marker. This keeps post-EOF xref/trailer/object garbage from becoming the selected root for:

- visible page text extraction;
- XMP and trailer Info metadata;
- catalog `/Names /EmbeddedFiles` extraction;
- attachment summary/preflight rows.

The existing object-owned ignored-`startxref` recovery remains intact: bytes after a prior EOF are still preserved when a valid classic xref table exists between that EOF and the ignored object-owned boundary.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildMissingStartxrefEofBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds missing-startxref classic rebuild scans before post-EOF xref garbage
Expected: 'current-missing-startxref-eof.xml'
Actual: 'decoy-missing-startxref-eof.xml'
1 test files, 13 assertions, 1 failures
```

## Verification

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildMissingStartxrefEofBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS bounds missing-startxref classic rebuild scans before post-EOF xref garbage
1 test files, 30 assertions, 0 failures
```

Adjacent classic xref family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildObjectOwnedStartxrefCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildUnclosedStartxrefCompositeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicFormFeedWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicPdfWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicCommentDelimiterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicGenerationOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildMissingStartxrefEofBoundaryCurrentBaseTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 986 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-missing-startxref-eof-boundary-currentbase.php
```

Emits `current_xref_before_eof=true`, `post_eof_xref_present=true`, `uses_current_page_text=true`, `keeps_current_metadata=true`, `imports_current_attachment=true`, `attachment_summary_current_only=true`, `post_eof_text_excluded=true`, `post_eof_metadata_excluded=true`, `post_eof_attachment_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted classic xref `/Prev` incremental selection, malformed numeric startxref repair, stale-but-valid startxref repair, selected-startxref EOF bounds, commented startxref rejection, object-owned ignored-startxref rebuild bounds, unclosed composite bounds, plus-signed headers, form-feed/NUL whitespace, row-state punctuation rejection, malformed/trailing/overdeclared subsection behavior, or generation-offset repair. The bounded behavior is specifically no-selectable-startxref classic rebuild recovery before post-EOF xref/trailer/object garbage.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, classic xref table parser, trailer/root selection, metadata extractor, EmbeddedFiles extractor, attachment summary path, and WordPress smoke renderer. GPU/model OCR, PDFium rendering, external PDF tools, live Surya/Texify/Torch execution, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
