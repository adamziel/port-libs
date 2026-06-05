# markerpdf-font-width-advance-boundary-current-base-20260605T205908Z

## Behavior

Native searchable-PDF text extraction now resolves Type0 `/DescendantFonts`
indirect references by exact object generation before reading CIDFont `/W`,
`/DW`, `/W2`, or `/DW2` width metrics. A referenced `4 0 R` descendant no
longer falls through to a stale same-object `4 1 obj` body when computing text
advance gaps or styled span bboxes.

The PDF fixture keeps the Type0 font dictionary at `/DescendantFonts [4 0 R]`
and places a stale `4 1 obj` CIDFont with inverted wide/thin widths later in
the file. Before the source edit, the current extractor emitted `Wide Thin` and
`ThinWide`. After the source edit, exact-generation width advances emit
`WideThin` and `Thin Wide`, with expected styled bboxes.

## Evidence

- Red-first focused test after adding the fixture:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
  -> `1 test files, 439 assertions, 1 failures`.
- After source edit:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
  -> `1 test files, 452 assertions, 0 failures`.
- Adjacent Type0/CID/CMap width family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php`
  -> `7 test files, 496 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php`
  emits `exact_generation_descendant_cid_widths_resolved=true`,
  `exact_generation_descendant_stale_generation_excluded=true`,
  `exact_generation_descendant_first_bboxes_preserved=true`,
  `exact_generation_descendant_second_bboxes_preserved=true`,
  `executes_python_or_models=false`, and
  `executes_external_pdf_tools=false`.

## Non-Overlap

This slice does not touch OCR, Surya/Texify/Torch/model workers, CMap stream
filter review, UseCMap inheritance, simple-font Widths generation handling,
FontDescriptor MissingWidth generation handling, Type3 FontMatrix behavior, or
xref repair. It is limited to descendant CIDFont body lookup for native width
advance extraction.

## Dependency Closure

No new support component is needed. The implementation reuses the existing
native PDF indirect-reference parser and exact-generation object-body resolver.
Root harness was not run because this is an isolated markerPDF micro-slice.
