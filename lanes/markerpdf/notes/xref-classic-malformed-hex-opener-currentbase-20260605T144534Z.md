# Classic XRef Rebuild Malformed Hex Opener Boundary

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T144534Z`
Base: `51459e38f0cb013b3051260a5ce3e3395d649067`

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to pdftext/PDFium, while this PHP lane owns the native parser boundary for classic xref-table repair before text, metadata, and attachment handoff. PDF hex strings are delimited by `<` and `>` and contain hexadecimal digits plus whitespace; a malformed non-hex `<...` opener before a later top-level `xref` table should not cause the rebuild scanner to treat the current table as token-owned and fall back to a stale previous root.

## Implementation

- `PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now use strict hex-string token skipping for xref/startxref/trailer scans.
- The stricter scanner skips only syntactically valid PDF hex strings and leaves malformed non-hex angle openers recoverable, so later top-level classic xref tables remain visible.
- General PDF value parsing keeps its existing broader hex-string helper where used outside the xref scan path.

## Evidence

Red-first focused test:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`
- Before implementation: failed the new malformed non-hex opener case by selecting stale page text.
- After implementation: `1 test files / 576 assertions / 0 failures`.

WordPress smoke:

- `php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-malformed-hex-currentbase.php`
- Emits current page text and reports `metadata_title_current=true`, `embedded_file_current=true`, `attachment_summary_current=true`, `rejects_stale_table=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted classic xref comment, literal-string, composite-token, row-state, zero-count, signed-startxref, `/Prev`, or root-free/free-row slices. It owns only malformed non-hex angle-openers before a current classic xref table and the duplicated native xref scanners that consume that boundary.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP PDF parsing helpers and remains inside the no-GPU markerPDF scope. OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, and external PDF tools remain intentionally excluded.

Root harness: not run - isolated micro-slice.
