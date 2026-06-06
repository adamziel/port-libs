# Font Width Malformed CID /W Array Current-Base Slice

Slice: `markerpdf-font-width-advance-boundary-current-base-20260606T170644Z`
Base: `cd0e5891c156b74b93e3a6ddb7bf05dd8f35f257`

## Behavior

Type0 descendant CIDFont horizontal `/W` array-form segments now fail closed when
any operand in the width list is malformed or non-finite. The segment is skipped
as a unit instead of accepting later narrow decoy widths that can create a false
WordPress paragraph gap.

The new fixture maps CIDs 1-9 to `WideBlock` with `/DW 1000` and a malformed
`/W [1 [1000 /Bad 250 250] 5 9 1000]`. The expected text stays `WideBlock`,
with span bboxes `[[0,0,48,12],[48,0,108,12]]`; the stale behavior produced
`Wide Block` by partially accepting the malformed width list.

## Implementation

`PdfTextExtractor::cidWidthsFromWArray()` now parses horizontal array-form
metric lists through a single finite-metric helper. Invalid entries reject only
that array-form segment, preserving existing valid range-form `/W`, valid
indirect `/W` arrays, `/DW` fallback, vertical `/W2`, and simple-font width
handling.

## Evidence

Red-first after adding the focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files / 571 assertions / 1 failure`; actual text line was
`Wide Block`.

After the implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files / 581 assertions / 0 failures`.

Adjacent font/CMap width family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(Font|CMap).*Width|Pdf.*Font.*CMap|PdfCMap.*Width' | sort)`

Result: `36 test files / 1327 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php`

Result: reports `malformed_cid_w_array_segment_rejected=true`,
`malformed_cid_w_array_false_gap_excluded=true`,
`malformed_cid_array_lines=["WideBlock"]`, and
`malformed_cid_array_span_bboxes=[[0,0,48,12],[48,0,108,12]]`.

## Non-Overlap

This does not repeat the accepted average simple-font fallback, quote spacing,
vertical `/W2`, indirect CID `/W`, negative first-CID `/W`, or Type3 FontMatrix
width slices. It only covers malformed horizontal CIDFont `/W` array-form
operands that previously leaked partial width metrics.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF parser,
font-width parser, CMap decoding, and WordPress smoke harness. No Python, OCR,
Surya, Texify, Torch, pypdfium, PIL, external PDF tools, or live-service
providers were invoked.
