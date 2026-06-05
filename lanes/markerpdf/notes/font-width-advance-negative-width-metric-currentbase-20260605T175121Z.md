# Font Width Advance Negative Width Metric Current Base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260605T175121Z`
Base: `3235f8b726c92b836d2ca5705bd55b61ba8c1970`

## Behavior

Malformed searchable PDFs can carry negative horizontal font advance metrics in
simple-font `/Widths` or CID `/W`/`DW` data. Those values are not valid
horizontal advance evidence for text extraction; accepting them can shrink the
current text end, create a false positioned word gap, and inflate styled span
bboxes.

`PdfTextExtractor` now filters horizontal font advance metrics through a
non-negative finite guard before they feed current-advance, glyph-width, and
CID width-evidence logic. Signed vertical `/W2` displacement metrics still use
the existing finite guard, preserving vertical-writing behavior where negative
displacements are expected.

## Evidence

- Red-first focused test:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
  => `1 test files, 410 assertions, 1 failures`; the new negative `/Widths`
  fixture emitted `AB CD` instead of `ABCD`.
- Passing focused test after source edit:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
  => `1 test files, 421 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php > /tmp/markerpdf-font-width-example.html`
  with decoded smoke check reports
  `negative_width_metric_rejected=true`,
  `negative_width_metric_false_gap_excluded=true`,
  `negative_width_metric_styled_bboxes_preserved=true`,
  `negative_width_metric_reversed_bbox_excluded=true`,
  `executes_python_or_models=false`, and
  `executes_external_pdf_tools=false`.

## Non-overlap

This is scoped to native searchable-PDF font advance handling. It does not
rework OCR/model behavior, object-stream xref repair, page geometry,
annotations, forms, metadata, or image/filter metadata. It preserves the
existing non-finite horizontal-width boundary and vertical CID `/W2` signed
displacement tests.

## Dependency Closure

No new support component is required. The patch reuses the existing native PHP
PDF parser, font metric extraction, and focused TestRunner harness. No Python,
GPU/model workers, external PDF tools, or online services are used.
