# markerPDF malformed CMap overdeclared literal-row filter boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T101858Z`

Accepted base: `ba5d3716ae151c5706a8fd13f14f0006f8bc18f9`

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction through PDF parser/font machinery rather than interpreting arbitrary bytes inside decoded ToUnicode CMap literal strings as additional operator rows. Under the current no-GPU lane scope, this patch ports the native parser boundary only: filtered CMap streams may be decoded, but row extraction must remain token-aware before searchable text import.

## Behavior

`PdfTextExtractor` now sanitizes CMap operator-block bodies before regex row matching for `bfchar`, `bfrange`, CID char/range blocks, and codespace ranges. PDF comments, literal strings, and dictionaries are blanked while real hex strings, arrays, names, numbers, and operator delimiters remain available to existing parsers.

The focused fixture uses a FlateDecode ToUnicode CMap with `2 beginbfchar`, one real mapping row, and a second hex-looking mapping only inside a decoded literal string. Before the fix, the red probe extracted `Overdeclared Literal Leak`. After the fix, the visible text is `Overdeclared Literal Safe Import` and the decoy remains excluded.

## Evidence

- Focused: `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php` -> `1 test files, 972 assertions, 0 failures`.
- Adjacent CMap/filter/DecodeParms family: `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/Pdf*CMap*Test.php` -> `21 test files, 1650 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php` exits `0` and emits `overdeclared_literal_decoded_cmap_count=1`, `overdeclared_literal_cmap_name=WPOverdeclaredLiteralBoundary-H`, `overdeclared_literal_decoy_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat prior malformed CMap work for dictionary/literal Filter operand rejection, null Filter DecodeParms slots, stale indirect generations, post-`endcmap` cleanup, second complete CMap programs, literal `CMapName` or `usecmap` decoys, Crypt/unsupported filter decisions, or stream filter stack repair. The new boundary is specifically decoded literal-string text inside an otherwise valid CMap operator block when the declared row count is overdeclared.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF scanner/filter/CMap extraction path and adds a bounded token sanitizer around operator block row matching. OCR, Surya/Texify/Torch, PDFium/model parity, and live external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
