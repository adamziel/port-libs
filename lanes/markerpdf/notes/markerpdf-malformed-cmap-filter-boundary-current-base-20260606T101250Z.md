# markerpdf-malformed-cmap-filter-boundary-current-base-20260606T101250Z

## Scope

- Lane: markerpdf
- Accepted base: `f2b77d802e93bb0b73e3302173738b4dc3701217`
- Cluster: native searchable-PDF ToUnicode CMap filter operand boundary.
- Non-overlap: this reuses the existing malformed CMap filter/decode review path and adds the missing scalar `null` `/Filter` boundary only. It does not repeat accepted array-tail, indirect-reference, stale-generation, duplicate-filter, unsupported-filter, post-Length, DecodeParms, CMap EOD, Type3, OCR, Surya/Texify/Torch, or model-worker surfaces.

## Behavior

PDF stream `/Filter null` means no decoder stack. A malformed stream dictionary such as `/Filter null /FlateDecode /Length ...` must still fail closed because the unkeyed decoder-looking name is a second filter operand, not a dictionary key. Before this patch, scalar `null` returned an empty filter stack without scanning for that trailing operand, so raw CMap bytes were parsed and leaked their ToUnicode mapping into extracted text.

The patch makes scalar null filters use the same trailing operand guard already used by direct name, indirect reference, and array filters, and exposes the extra operand in `extractCMapStreamFilterLengthOwnerReview()`.

## Evidence

- Red-first before source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapScalarNullFilterExtraBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 2 assertions, 2 failures`
  - Failure: both fixtures returned `Null ... CMap Leak...Safe Import` instead of only the safe import text.
- Focused after source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapScalarNullFilterExtraBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 108 assertions, 0 failures`
- Adjacent malformed CMap filter/decode family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapScalarNullFilterExtraBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFallbackStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`
  - Result: `5 test files, 1862 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-scalar-null-filter-currentbase.php`
  - Result: emitted one paragraph, `safe_text_preserved=true`, `payload_excluded=true`, `decoded_cmap_count=0`, `invalid_filter_operand_count=1`, `malformed_filter_operand_count=1`, `filter_operand_policy=reject_malformed_filter_operands`, `extra_filter_name=FlateDecode`, `decoded_with_current_operands=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The slice stays inside the existing native PHP PDF tokenizer, stream-filter review, CMap extraction, and WordPress smoke harness. GPU/model OCR, external PDF tools, pypdfium, PIL, and live-service workers remain intentionally out of scope under the markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
