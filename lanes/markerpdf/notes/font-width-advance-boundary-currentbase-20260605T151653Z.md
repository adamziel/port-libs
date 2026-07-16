## Font Width Advance Boundary

Slice: `markerpdf-font-width-advance-boundary-current-base-20260605T151653Z`
Base: `91de4ed62d6b0b7fdd5499395c7b7dbc88f92c5e`

### Behavior

`PdfTextExtractor::parseCidCMap()` now initializes local CMap code-space ranges before parsing `begincidrange` blocks. This matches the existing ToUnicode CMap path and keeps vertical Type0 Encoding CMaps with `/WMode 1` from passing an undefined/null code-space range set into `parseCidRanges()`.

This is a native searchable-PDF text/font parser boundary. It does not run OCR, Surya, Texify, Torch, Python models, PDFium, or external PDF tools.

### Red-First Evidence

Before the patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files / 336 assertions / 4 failures`. The vertical CIDFont `/W2` cases failed because `parseCidRanges()` received null code-space ranges and PHP warned about undefined `$cidRangeCodeSpaceRanges`.

After the patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files / 380 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php >/tmp/markerpdf-font-width-example.html`

Result: exit `0`; emitted WordPress paragraphs and review metadata for simple-font widths, vertical CIDFont `/W2`, indirect W/W2 metrics, Type3 FontMatrix advances, and no model/external-tool execution.

### Dependency Closure

No new support component is needed. This reuses the existing native CMap parser and stream/text extraction helpers under the current no-GPU markerPDF scope.

### Next

Continue with non-overlapping font/CMap boundaries, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
