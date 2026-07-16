# markerPDF malformed CMap indirect array-tail filter boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260606T093228Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, with low-level font and ToUnicode CMap stream decoding delegated to pdftext/PDFium before Markdown and WordPress paragraph assembly.

The native no-GPU PHP lane owns that parser boundary. A stream `/Filter` value may be a name, array of names/null identity slots, or an indirect object containing exactly one such value. If a selected indirect filter helper object has a valid array followed by a trailing unkeyed decoder name, such as:

```text
7 0 obj
[ /FlateDecode ] /ASCIIHexDecode
endobj
```

the CMap remap must fail closed and WordPress review metadata should identify the trailing operand that made the helper malformed.

## Behavior

`PdfTextExtractor::xrefStreamIndirectOperandReview()` now attaches the first trailing token after a selected indirect filter helper's single value to the filter operand review. This aligns indirect helper arrays with the existing direct scalar/direct array extra-operand diagnostics.

The focused fixture keeps visible text as `Indirect Array Tail Safe Import`, rejects the compressed ToUnicode CMap that would remap text to `Indirect Array Tail CMap Leak`, and records:

- `filter_operand_policy=reject_malformed_filter_operands`
- `filter_operands[0].token_type=array`
- `filter_operands[0].extra_filter_operand=true`
- `filter_operands[0].extra_filter_name=ASCIIHexDecode`
- `decoded_cmap_count=0`

## Evidence

Red-first inline probe on accepted base `3f8ff858e83ffe66ab1e60b8b757f837d5955701` before the source edit:

```text
text=Indirect Array Tail Safe Import
decoded=0
invalid=1
malformed=1
policy=reject_malformed_filter_operands
token_type=array
extra=no
preview=[ /FlateDecode ] /ASCIIHexDecode
```

Focused green after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectArrayTailFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS classifies trailing operands after selected indirect CMap Filter arrays before text extraction
1 test files, 66 assertions, 0 failures
```

Adjacent malformed CMap/filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectArrayTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapReferenceExtraFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapPostDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthOperandFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnknownFilterNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDanglingFilterNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFallbackStreamBoundaryCurrentBaseTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 2188 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-indirect-filter-array-tail-currentbase.php
markerpdf-cmap-indirect-filter-array-tail-currentbase-smoke
safe_text_preserved=yes
leaking_cmap_text_excluded=yes
indirect_array_tail_rejected=yes
extra_filter_operand_recorded=yes
extra_filter_name=ASCIIHexDecode
decoded_cmap_count=0
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct array filter tails before `/Length`, scalar filter extra operands, indirect scalar helper extra operands, indirect filter references followed by top-level extra names, direct dictionary/literal filter operands, indirect array dictionary operands, stale-generation filter-owner selection, duplicate `/Filter` declarations, unknown/escaped/unsupported filter names, CMap filter EOD handling, DecodeParms fail-closed behavior, post-`endcmap` parser bounding, CMap source-width work, xref repair, image filters, metadata, annotations, forms, OCR, or model execution.

The bounded behavior here is specifically review metadata for trailing operands inside selected indirect filter helper objects whose first value is an array.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, xref owner selection, stream dictionary tokenizer, indirect object resolver, filter resolver, ToUnicode CMap parser, Identity-H fallback path, and WordPress smoke renderer. Full upstream model/PDFium parity, live OCR/layout/table/equation models, raster rendering, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Next Task

Continue with non-overlapping native searchable-PDF parser and converter boundaries: fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
