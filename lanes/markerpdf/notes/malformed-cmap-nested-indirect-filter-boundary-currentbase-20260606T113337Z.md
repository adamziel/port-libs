# Malformed CMap Nested Indirect Filter Boundary

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260606T113337Z`

Accepted base: `454eb2e80ab750c1392b21e50662320bbde7c428`

## Source Truth

Upstream markerPDF reaches ToUnicode CMaps through pdftext/PDFium font decoding. In the native no-GPU PHP parser, CMap stream `/Filter` operands must be resolved through the current PDF object graph before any decoded CMap payload can affect WordPress text import. A dictionary reached through an indirect filter operand is not a decoder name and must fail closed as dictionary-backed metadata, not as a generic malformed token.

## Implementation

- `PdfTextExtractor` now resolves indirect references while classifying CMap filter operands for dictionary-backed values.
- The decoder remains fail-closed: nested indirect dictionary filter operands still suppress ToUnicode decoding, preserve fallback searchable text, and keep CMap payload/dictionary decoys out of visible text.
- Added focused PHP coverage for `/Filter [ 8 0 R /FlateDecode ]` where `8 0 R` resolves to `9 0 R`, and `9 0 R` is a dictionary.
- Added a WordPress smoke example for the same boundary.

## Evidence

Red-first before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapNestedIndirectDictionaryFilterBoundaryCurrentBaseTest.php`

Result: `FAIL`, expected `dictionary_filter_operand_count` `1`, actual `0`; `1 test files, 20 assertions, 1 failures`.

After the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapNestedIndirectDictionaryFilterBoundaryCurrentBaseTest.php`

Result: `PASS`; `1 test files, 59 assertions, 0 failures`.

Adjacent CMap/filter family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapNestedIndirectDictionaryFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMap*Filter*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilter*CurrentBaseTest.php`

Result: `PASS`; `18 test files, 2708 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-nested-indirect-filter-boundary-currentbase.php`

Flags: `fallback_text_preserved=true`, `nested_indirect_dictionary_filter_rejected=true`, `dictionary_operand_classified=true`, `generic_malformed_operand_not_used=true`, `decoded_cmap_suppressed=true`, `cmap_payload_excluded=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object graph, CMap stream review, and stream-filter boundary code already in `PdfTextExtractor`. No Python, OCR, GPU model, raster backend, PDFium process, or external PDF tool was run.

## Non-Overlap

This does not repeat the accepted Type3 CharProc marked-content property slice or the existing direct/selected indirect CMap filter-array dictionary tests. The new boundary is specifically a nested indirect filter operand chain that resolves to a dictionary before CMap decoding.
