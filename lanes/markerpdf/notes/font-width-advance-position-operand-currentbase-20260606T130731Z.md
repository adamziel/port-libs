## Font Width Advance Position Operand Boundary

Slice: `markerpdf-font-width-advance-boundary-current-base-20260606T130731Z`

Base: `d7dd35e193e433506c4031446b30b2cc5f04e717`

Behavior: native searchable-PDF text extraction now rejects overlarge finite `Tm` and `Td` text-position operands through the existing font-advance metric guard before word-gap grouping and styled-span bbox geometry. This preserves compact WordPress paragraph text and bounded bboxes when a PDF contains huge finite coordinates or text-matrix scale operands.

Red-first evidence before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvancePositionOperandBoundaryCurrentBaseTest.php`

Result: failed after 1 assertion because extracted lines included `AB CD` and `EF GH` from overlarge finite position operands.

Focused evidence after the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvancePositionOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 21 assertions, 0 failures`.

Adjacent family evidence:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`

Result: `4 test files, 1270 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-position-operand-currentbase.php > /tmp/markerpdf-font-width-position-operand-currentbase.html`

Result: smoke output reports `overlarge_tm_x_rejected=true`, `overlarge_td_x_rejected=true`, `overlarge_tm_scale_rejected=true`, `false_word_gaps_excluded=true`, `styled_bboxes_preserved=true`, `bbox_numbers_finite=true`, `max_bbox_magnitude=48`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure: no new support component is needed. The slice reuses the native PHP text/content-stream parser and existing font-advance metric boundary; it does not invoke Python, OCR, pypdfium, PIL, GPU/model execution, or external PDF tools.

Non-overlap: this does not revisit accepted CMap/font width arrays, quote operators, `TJ`, text state spacing, Type0/Type3 widths, malformed font metrics, xref repair, metadata, annotation review, or image/filter behavior. The only changed production behavior is magnitude-bounding text-position/text-matrix operands before font-width advance grouping.
