# markerPDF vertical font-width composed advance boundary

Session: `port-dev-markerpdf-font-width-advance-20260608T164051Z`
Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260608T164051Z`
Base accepted HEAD: `f548c0e7c0c0e27d77af5a4032e60b4aaf51015e`

## Scope

This isolated no-GPU markerPDF slice maps a native searchable-PDF text-advance boundary for vertical Type0/CID fonts. Upstream markerPDF relies on parser/PDFium/pdftext geometry for searchable PDFs before WordPress Markdown/block import, so the PHP fallback must keep composed glyph advances bounded before styled span geometry drives layout.

The new behavior covers vertical CIDFont `/W2` displacements where each individual metric is finite and accepted, but a long text operand composes those metrics into an absurd vertical current-text advance. `PdfTextExtractor` now applies the same bounded composed-advance fallback used by horizontal text to vertical advances, preserving decoded text while keeping styled bboxes finite and reviewable.

## Red-First Evidence

Before the extractor change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceVerticalComposedBoundaryCurrentBaseTest.php`

failed with `1 test files, 6 assertions, 1 failures`; the styled span bbox height was `120000.0` instead of the bounded fallback height `1200.0`.

## Verification

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceVerticalComposedBoundaryCurrentBaseTest.php`

Result: `1 test files, 10 assertions, 0 failures`.

Adjacent font-width family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceVerticalComposedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceScalarOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceMalformedW2ArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthRangeOperandAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php`

Result: `9 test files, 802 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-vertical-composed-currentbase.php`

Result: exits `0`; emits `bounded_vertical_advance=true`, `span_bboxes=[[0,0,12,1200]]`, `line_bbox=[0,0,12,1200]`, `max_bbox_magnitude=1200`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PHP pass cases: `+1`
- Focused assertions: `+10`
- WordPress smoke scenarios: `+1`
- `phpPass`: `3304 -> 3305`
- `wordpressScenarios`: `2692 -> 2693`
- Mapped upstream denominator: unchanged; this remains within the native font-width/text-advance behavior cluster.

## Non-Overlap

This does not repeat the accepted horizontal composed `/Widths` advance fallback, default `/DW` guards, scalar and array-form `/W` operand guards, malformed `/W2` tuple guards, vertical writing-mode movement tests, Type3 FontMatrix advances, text-state spacing operators, page font resource lookup, ToUnicode/CMap segmentation, object-stream font slices, or any OCR/model behavior. The new boundary is specifically the composed vertical advance delta from many accepted CID `/W2` metrics before styled-span bbox generation.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, CMap parser, Type0/CIDFont width parser, text operand source extraction, vertical text advance estimator, styled-span bbox path, and WordPress smoke renderer. GPU/OCR/model execution, Surya/Texify/Torch workers, external PDF tools, raster rendering, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.

## Next Task

Continue non-overlapping native searchable-PDF behavior around font resources, CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter review metadata, or supplied-boundary table/equation handoffs.
