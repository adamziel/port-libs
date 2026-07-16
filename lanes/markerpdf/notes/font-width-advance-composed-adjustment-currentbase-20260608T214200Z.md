# Font Width Advance Composed Adjustment Current Base

- Date: 2026-06-08 UTC
- Lane: markerpdf
- Slice: markerpdf-font-width-advance-boundary-current-base-20260608T214200Z
- Accepted base: a0d85bbfea71fbea16acdfcda87bce21bb3681b0

## Source Truth

PDF `TJ` array numbers are text-positioning adjustments that compose with the
current font size and horizontal scale before changing the text cursor. The
native markerPDF port already bounded individual numeric operands and glyph
metrics, but the accepted base still let an individually valid adjustment
multiply by an individually valid font size into an unbounded cursor delta.

The red-first fixture uses `/Fadj 2000 Tf` and a `TJ` adjustment of `100000`.
Both operands are within the existing scalar guard, but their composed delta is
`200000` text units. Before the source edit, that produced a false `AB C`
WordPress line and oversized styled span geometry.

## Patch

- Bounded composed horizontal `TJ` adjustment deltas in
  `PdfTextExtractor::adjustTextEndX()` before applying them to cursor state.
- Applied the same composed-delta guard to vertical `TJ` adjustments in
  `adjustTextEndY()`.
- Added a focused current-base test for text lines, runs, plain text, naive
  text, styled span bboxes, finite bbox numbers, and resource-payload
  exclusion.
- Added a WordPress smoke that renders the bounded result as a Gutenberg
  paragraph and records `executes_python_or_models=false` and
  `executes_external_pdf_tools=false`.

## Verification

- Red-first before implementation:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceComposedAdjustmentBoundaryCurrentBaseTest.php`
  - `1 test files, 1 assertions, 1 failures`
  - failed with actual text line `AB C`
- After implementation:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceComposedAdjustmentBoundaryCurrentBaseTest.php`
  - `1 test files, 13 assertions, 0 failures`
- Broader font-width current-base group:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidth*CurrentBaseTest.php lanes/markerpdf/tests/PdfFont*Width*CurrentBaseTest.php lanes/markerpdf/tests/PdfCMap*Width*CurrentBaseTest.php`
  - `85 test files, 2367 assertions, 0 failures`
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-composed-adjustment-currentbase.php`
  - exits 0 with `visible_text=ABC`, `styled_bboxes_bounded=true`, and
    `max_bbox_magnitude=6000`

## Dependency Closure

No new support component is required. This reuses the existing native PHP text
advance boundary helper (`textAdvanceDeltaIsBounded()`) in the text-adjustment
path. No Python, OCR/model worker, external PDF tool, raster renderer, or live
service is invoked.

## Non-Overlap

This does not repeat accepted simple-font width arrays, CIDFont `/W`/`/W2`,
font-size operand rejection, overlarge raw `TJ` operand rejection, horizontal
scale rejection, relative `Td`, absolute `Tm`, ExtGState font arrays, Type3
FontMatrix, CMap source-width, DCTDecode, xref, metadata, OCR, or GPU/model
work. The owned behavior is the composed cursor adjustment after valid scalar
operands multiply together.

## Next Task

Continue with a non-overlapping native searchable-PDF slice, preferably CMap
or font resource scoping, page geometry, stream-filter recovery, xref repair,
metadata, annotations/forms, image/filter metadata, or supplied-boundary
table/equation handoff behavior.
