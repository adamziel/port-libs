# markerpdf malformed CMap scalar filter-value boundary current-base

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260607T013530Z`

Base: `f045041a3e9bc718c4c62b84783de136a8a23e7f`

## Upstream/source-truth boundary

PDF stream dictionaries accept `/Filter` as a filter name, an array of filter names, or an indirect object resolving to that shape. This slice covers malformed direct scalar CMap filter values, specifically `/Filter true` and `/Filter 1.5`, before `/Length`.

The native parser now exposes those direct operands with type-accurate structured review values (`true` and `1.5`) while continuing to fail closed: the compressed ToUnicode CMap is not decoded, malformed filter operands are counted, and WordPress-visible text falls back to the safe source bytes instead of importing the CMap leak.

## Non-overlap

This does not repeat the accepted malformed CMap dictionary/literal operands, indirect scalar/literal helper operands, extra unkeyed filter names after a valid scalar filter, null-filter extra operands, array tail operands, DecodeParms boundaries, unsupported filter names, escaped filter names, duplicate filter declarations, stream-filter EOD comment handling, or xref/object-stream filter owner slices. The owned surface is direct boolean/real-number `/Filter` values on a CMap stream plus typed scalar review values.

## Focused verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapScalarFilterValueBoundaryCurrentBaseTest.php`
  - `1 test files, 96 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnknownFilterNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapScalarNullFilterExtraBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapScalarFilterValueBoundaryCurrentBaseTest.php`
  - `4 test files, 1809 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-scalar-filter-value-currentbase.php --self-test`
  - `self_test_passed=true`, both boolean and number cases report `filter_operand_policy=reject_malformed_filter_operands`, `filter_resolution_failed=true`, `decoded_cmap_count=0`, `typed_value_matches=true`, and no Python/model/OCR or external PDF tool execution.

## Dependency closure

No new dependency or support component is required. This reuses the existing native `pdf-text-dictionary-core` stream dictionary, CMap filter review, and searchable-PDF text extraction path.

## Next

Continue non-overlapping native markerPDF CMap/font/filter work around remaining searchable-PDF parser boundaries, especially xref repair, page geometry, metadata, annotations/forms, image/filter metadata, and supplied-boundary table/equation handoffs under the no-GPU scope.
