# Malformed CMap Indirect DecodeParms Null Filter Boundary

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T055622Z`

Base: `faa78576a7e937b1a3569a086f4da2a3cae63756`

## Source Truth

Upstream `sddai/markerPDF` is pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The upstream searchable-PDF path relies on pdftext/PDFium-style stream decoding and ToUnicode CMap parsing. Under the current no-GPU markerPDF scope, this slice ports the native PHP parser boundary for CMap stream filter stacks and DecodeParms handling; no OCR, model, or external PDF tool execution is involved.

## Behavior

The previous direct-array null-filter handling worked for:

`/Filter [ null /FlateDecode ] /DecodeParms [ 99 0 R << /Predictor 1 >> ]`

but an indirect DecodeParms array:

`/Filter [ null /FlateDecode ] /DecodeParms 8 0 R`, where `8 0 R` is `[ 99 0 R << /Predictor 1 >> ]`

was resolved through the generic DecodeParms path before slot alignment. The unresolved `99 0 R` in the null-filter slot poisoned the whole CMap stream, so the valid FlateDecode parameter was never used.

`PdfTextExtractor` now resolves indirect DecodeParms arrays before applying the same slot-aware null-filter alignment used for direct arrays. Review metadata still records object `8`, token type `array`, and the array preview, while `invalid_decodeparms_operand_count` and `invalid_decodeparms_parameter_count` stay `0` for the ignored null slot.

## Evidence

Red-first focused run after adding the fixture and before the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 660 assertions, 1 failures`; the new case expected `['Indirect Null Slot CMap Import']` and extracted `[]`.

Focused run after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 712 assertions, 0 failures`.

Adjacent CMap/stream-filter family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`

Result: `6 test files, 1548 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php`

Result: exits `0` and emits `indirect_null_filter_decodeparms_slot_ignored=true`, `indirect_null_filter_decodeparms_array_object=8`, `indirect_null_filter_decodeparms_invalid_operand_count=0`, `indirect_null_filter_decodeparms_invalid_parameter_count=0`, `leaking_cmap_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This is not a repeat of direct null-filter DecodeParms arrays, trailing malformed DecodeParms parameters, UseCMap DecodeParms inheritance, identity/private Crypt filters, post-`endcmap` parser boundaries, unsupported CMap filters, or generic stream-stack indirect null filter handling. The new boundary is specifically an indirect DecodeParms array object whose unresolved element is aligned to a null filter slot in a ToUnicode CMap stream.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP object resolver, stream dictionary parser, filter stack decoder, Flate decoder, DecodeParms predictor handling, CMap parser, and WordPress smoke harness. GPU/model/OCR execution remains intentionally out of scope under the markerPDF lane override.

