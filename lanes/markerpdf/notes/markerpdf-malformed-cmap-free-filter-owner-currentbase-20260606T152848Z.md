# Malformed CMap Free Filter Owner Boundary

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260606T152848Z`
Base: `3d6e6a3622decb12b82b423840061172715fe0f2`

## Scope

This slice covers a native searchable-PDF parser boundary for ToUnicode CMap stream filters. It is non-overlapping with earlier malformed CMap filter operand slices that covered null operands, array tails, indirect scalar helpers, indirect array helpers, DecodeParms helpers, and reference-extra operands. The new boundary is a current xref row that marks the indirect `/Filter` helper object free while stale direct object bytes still appear in the file.

## Source Truth

Upstream markerPDF relies on native PDF text extraction behavior for searchable PDFs before model/OCR paths. Under the no-GPU lane scope, this port owns the native parser review boundary. A current xref free row means the helper object is not selected for the current revision, so stale direct object text must not be decoded, trusted, or exposed as a valid current `/Filter` operand.

## Behavior

The fixture uses a ToUnicode CMap stream with `/Filter 7 0 R`. Object `7 0 obj` contains a stale `/FlateDecode` helper body, but the current xref row for object 7 is `f`. `PdfTextExtractor::xrefStreamIndirectOperandBody()` now returns unresolved for that free current xref entry instead of falling back to stale direct object bytes.

The searchable text remains `Free Helper Safe Import`. The tainted CMap is not decoded, the review reports one unresolved invalid filter operand, and the operand metadata has `owner_policy=free_xref_entry`, `resolved=false`, `xref_selected=false`, and `xref_entry_type=0` without a stale `value_preview`.

## Evidence

- Red-first focused test:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFreeFilterOwnerBoundaryCurrentBaseTest.php`
  Result: `1 test files, 51 assertions, 1 failures` because the stale helper body still resolved.
- Focused after fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFreeFilterOwnerBoundaryCurrentBaseTest.php`
  Result: `1 test files, 64 assertions, 0 failures`.
- Adjacent malformed CMap filter boundary family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFreeFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapReferenceExtraFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectHelperDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectArrayTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapScalarNullFilterExtraBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterPostLengthBoundaryCurrentBaseTest.php`
  Result: `9 test files, 2217 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-free-filter-owner-currentbase.php`
  Result: emits a Gutenberg paragraph for `Free Helper Safe Import` and flags `helper_owner_policy=free_xref_entry`, `helper_resolved=false`, `helper_xref_selected=false`, `helper_xref_entry_type=0`, `stale_helper_body_exposed=false`, and no Python/models/external PDF tools.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, xref selection, CMap filter operand review, and WordPress smoke path. OCR/model/PDFium/PIL/external-tool execution remains intentionally out of scope for this no-GPU markerPDF lane.

## Next

Continue with non-overlapping native markerPDF parser boundaries around CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table or equation handoffs.
