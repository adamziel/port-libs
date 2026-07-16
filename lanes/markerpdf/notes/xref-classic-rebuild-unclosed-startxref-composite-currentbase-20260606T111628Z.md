# markerPDF Classic XRef Rebuild Unclosed Startxref Composite Boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260606T111628Z`

Accepted base: `f3e6ef9e9a7803edbdb9db6d76cbe13ebbfcd147`

## Source Truth

markerPDF's searchable-PDF path delegates native PDF parsing to PDFium/pdftext upstream. In the no-GPU PHP lane, classic xref rebuild must therefore preserve the PDF parser boundary: an `xref` table is only selectable as a top-level PDF keyword, and raw `startxref` bytes inside an unterminated top-level dictionary or array do not close that composite token.

## Behavior

The focused fixture builds a current classic xref table with current page text, XMP/Info metadata, and an EmbeddedFiles name tree. It then appends decoy objects and an unterminated top-level dictionary containing raw `startxref` bytes before a fake classic `xref` table.

Before this patch, `xrefCandidateStartsAfterUnclosedTopLevelComposite()` reset its scan after any raw `startxref` bytes, so it missed the still-open dictionary and selected the decoy xref table during rebuild. The native text, metadata, EmbeddedFiles, and attachment preflight paths now keep the open-composite boundary active until a real `endobj` or `%%EOF` boundary, preventing the decoy page, metadata, and attachment from replacing current WordPress import content.

## Evidence

Red-first focused run after adding the fixture:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildUnclosedStartxrefCompositeCurrentBaseTest.php`

Result: `1 test files, 4 assertions, 1 failures`; the parser imported `Unclosed startxref decoy page` / `Composite startxref root leak`.

Focused green after source repair:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildUnclosedStartxrefCompositeCurrentBaseTest.php`

Result: `1 test files, 30 assertions, 0 failures`.

Adjacent classic xref rebuild family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicUnterminatedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildUnclosedStartxrefCompositeCurrentBaseTest.php`

Result: `3 test files, 723 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-unclosed-startxref-composite-currentbase.php`

Expected markers include `uses_current_classic_trailer_root=true`, `keeps_current_metadata_root=true`, `imports_current_attachment=true`, `attachment_summary_current_only=true`, `excludes_unclosed_composite_page=true`, `excludes_unclosed_composite_metadata=true`, `excludes_unclosed_composite_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF tokenizer, direct-object scanner, classic xref rebuild selector, metadata extractor, EmbeddedFiles extractor, and attachment preflight summarizer. GPU/OCR/model execution, PDFium rendering, Surya/Torch, Texify, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted damaged startxref rebuild, stale older-table startxref rebuild, post-EOF xref garbage, comments, array/composite-contained decoys, name-token `/startxref`, name-delimited `xref/Decoy`, name-offset `/xref`, linearized hint-table startxref, malformed rows, overdeclared counts, unterminated dictionary xref decoys, or stream-owned trailer/composite skip coverage. The bounded behavior is only raw `startxref` bytes inside an unclosed top-level composite before a fake classic xref table.
