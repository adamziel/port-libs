# markerpdf-classic-xref-rebuild-boundary-current-base-20260605T221654Z

## Source truth

- Upstream markerPDF pinned source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream searchable-PDF extraction delegates PDF text extraction to pdftext/PDFium. This no-GPU slice ports the native PHP parser boundary that chooses the searchable PDF object graph before any model/OCR handoff.
- Accepted base avoided: previous duplicate page `/Resources` current-base slice. This patch stays in classic xref rebuild repair and does not touch page resource lookup, OCR/model workers, Surya/Texify/Torch, or external PDF tools.

## Behavior

Classic xref rebuild now preserves rows parsed from a first/current xref subsection when the subsection declares too many rows but the table body ends cleanly at the `trailer` boundary. Before this patch, a current table shaped as `0 13` with valid rows `0..10` was discarded because there was no earlier completed subsection, causing text, XMP/Info metadata, and EmbeddedFiles attachment extraction to fall back to the stale `/Prev` xref table.

Malformed active rows still fail closed: the existing malformed-row, punctuation-state, malformed-leading-header, and malformed-trailing-subsection cases remain covered by the same focused test file.

## Evidence

- Red-first focused run after adding fixture:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`
  Result: `1 test files, 637 assertions, 1 failures`.
  Failure: expected `Current overdeclared-count xref page`, actual `Stale overdeclared-count xref page`.
- Post-fix focused run:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`
  Result: `1 test files, 663 assertions, 0 failures`.
- Adjacent sweep:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassic*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php`
  Result: `13 test files, 1220 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-overdeclared-count-currentbase.php`
  Result: emitted `overdeclared_count_repaired=true`, `current_import_kept=true`, `stale_xref_excluded=true`, `current_attachment=current-overdeclared-count-xref.xml`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- PHP lint:
  `php -l` passed for changed PHP source files, the focused test, and the new WordPress smoke example.
- Diff hygiene:
  `git diff --check -- lanes/markerpdf` passed with no output.

## Dependency closure

No new support component is needed. The patch reuses the existing native PHP classic xref parser, text extractor, metadata extractor, embedded-file extractor, attachment preflight, and page-property xref parsing helpers. GPU/model/OCR parity remains an intentional no-GPU scope limit, not a blocker for this searchable-PDF behavior.

## Next

Continue with non-overlapping markerPDF native parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
