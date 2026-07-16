# markerpdf-malformed-cmap-filter-boundary-current-base-20260606T105615Z

## Scope

- Lane: markerpdf
- Accepted base: `acaa655f41a326695b1b8edaa14a30da83e3ddae`
- Cluster: native searchable-PDF ToUnicode CMap stream filter operand boundary.
- Non-overlap: this tightens the already accepted malformed CMap length/filter boundary by preserving CMap review evidence for array `/Filter [...]` dictionaries when a valid `/Length` operand is followed by an unkeyed post-Length operand. It does not repeat scalar post-Length filter operands, pre-Length array tails, null filters, DecodeParms alignment, CMap EOD, Type3, OCR, Surya/Texify/Torch, or model-worker surfaces.

## Behavior

PDF stream dictionaries can order `/Filter` and `/Length` independently, but an unkeyed decoder-looking token after the `/Length` value is not a valid dictionary entry. Before this patch, array filters stopped scanning when they reached `/Length`, so a malformed CMap stream such as `/Filter [ /FlateDecode ] /Length n /ASCIIHexDecode` was rejected by generic length-tail validation before the CMap review path saw it. Text extraction remained fail-closed, but WordPress review metadata lost the CMap stream entry and malformed filter operand evidence.

The patch makes array-filter scanning skip a well-formed `/Length` value and continue looking for unkeyed decoder or malformed operands, matching the existing scalar filter behavior. The CMap stream now remains review-visible with `filter_resolution_failed`, `reject_malformed_filter_operands`, and `decoded_cmap_count=0`, while visible text falls back safely without leaking the decoded CMap program.

## Evidence

- Red-first ad hoc probe before source edit:
  - `php -r '...'`
  - Result: safe text extracted, but `extractCMapStreamFilterLengthOwnerReview()` returned `cmap_stream_count=0` and `entries=[]` for `/Filter [ /FlateDecode ] /Length n /ASCIIHexDecode`.
- Focused new dictionary-extra test:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterPostLengthBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 59 assertions, 0 failures`.
- Updated existing name-extra boundary:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthOperandFilterBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 58 assertions, 0 failures`.
- Adjacent malformed CMap/filter/stream stack family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterPostLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapReferenceExtraFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthOperandFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`
  - Result: `8 test files, 2283 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-length-filter-boundary-currentbase.php --self-test`
  - Result: `self_test_passed=true`, `array_filter_post_length_operand_rejected=true`, `cmap_review_entry_preserved=true`, `cmap_stream_count=1`, `decoded_cmap_count=0`, `invalid_filter_operand_count=1`, `malformed_filter_operand_count=1`, `filter_operand_policy=reject_malformed_filter_operands`, `extra_filter_name=ASCIIHexDecode`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The slice stays inside the existing native PHP PDF tokenizer, stream dictionary scanner, stream-filter review, CMap extraction, and WordPress smoke harness. GPU/model OCR, external PDF tools, pypdfium, PIL, Surya, Texify, Torch, and live-service workers remain intentionally out of scope under the markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
