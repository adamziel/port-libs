# markerpdf malformed CMap duplicate DecodeParms boundary current-base

- Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260607T065726Z`
- Base accepted HEAD: `b521df4fbb89234aa253f16c929f895b657391e0`
- Scope: native PHP searchable-PDF parser behavior only. No OCR, Surya, Texify, Torch, GPU/model workers, raster execution, external PDF tools, or live services.

## Behavior

ToUnicode CMap streams with duplicate top-level `/DecodeParms` declarations now surface a specific duplicate-declaration review boundary. The new fixture uses `/DecodeParms` plus escaped `/Decode#50arms`; text extraction fails closed before decoding the forged CMap and falls back to the safe Identity-H text, while `extractCMapStreamFilterLengthOwnerReview()` reports:

- `duplicate_decodeparms_declaration_count = 1`
- `filter_end_marker_policy = reject_duplicate_decodeparms_declarations`
- `filter_decode_policy = reject_duplicate_decodeparms_declarations`
- `decodeparms_operand_policy = reject_duplicate_decodeparms_declarations`

This is intentionally non-overlapping with the existing malformed CMap filter operand, duplicate `/Filter`, escaped key success, named UseCMap, CMap EOD, CCITT Fax duplicate DecodeParms, image XObject, inline-image, and attachment stream-filter slices. It only adds CMap-specific duplicate DecodeParms preflight evidence and preserves current text fallback behavior.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateDecodeParmsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects duplicate escaped CMap DecodeParms declarations before current-base text extraction
Values are not identical
Expected: 1
Actual: NULL

1 test files, 20 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateDecodeParmsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate escaped CMap DecodeParms declarations before current-base text extraction

1 test files, 49 assertions, 0 failures
```

Related CMap filter family after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateDecodeParmsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapEscapedKeyFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateFilterBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
36 PASS cases
4 test files, 1743 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-import.php --self-test
{"self_test_passed":true,"scenario":"wordpress_pdf_malformed_cmap_duplicate_decodeparms_filter_import","paragraphs":["WP Duplicate DecodeParms Import"],"safe_fallback_text":true,"duplicate_decodeparms_rejected":true,"escaped_decodeparms_key_count":1,"filter_operand_policy":"filters_resolved","filter_decode_policy":"reject_duplicate_decodeparms_declarations","decodeparms_operand_policy":"reject_duplicate_decodeparms_declarations","executes_python_or_models":false,"executes_external_pdf_tools":false}
```

## Dependency Closure

No new support component is required. The slice reuses the existing native PHP PDF dictionary tokenizer, stream filter resolver, DecodeParms parser, CMap decoder/review pipeline, and WordPress Gutenberg paragraph smoke path.

## Next

Continue with non-overlapping native markerPDF parser work: CMap/font encoding and width edges, xref repair, metadata, annotations/forms, image/filter metadata, security preflight, page geometry, and supplied-boundary table/equation handoffs.
