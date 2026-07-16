# malformed-cmap-escaped-filter-name-boundary-currentbase-20260605T082854Z

Base accepted HEAD: `53ab09ea08cd1736ca31304608b22717d98cd5ee`

## Scope

This isolated markerPDF slice extends the native no-GPU PDF parser review metadata for ToUnicode CMap stream `/Filter` operands:

- valid escaped PDF filter names such as `/Fl#61teDecode` are normalized to `FlateDecode`, decoded, and counted as escaped filter-name operands;
- escaped unsupported names such as `/DCT#44ecode` are normalized to `DCTDecode`, counted as escaped filter-name operands, and still fail closed with `reject_unsupported_filter_names`;
- CMap bytes, filter implementation details, and leak text remain excluded from visible WordPress paragraphs;
- no Python, model worker, OCR, PDFium, image renderer, or external PDF tool is executed.

## Evidence

Focused test before this additive slice already passed:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`

Result before additive assertions: `1 test files, 826 assertions, 0 failures`.

Focused test after source/test update:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 899 assertions, 0 failures`.

Delta: `+2` focused PASS cases and `+73` focused assertions in the assigned parser/CMap/filter boundary test file.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-escaped-filter-boundary-currentbase.php`

Result: exits `0` and reports `valid_filters=["FlateDecode"]`, `unsupported_filters=["DCTDecode"]`, valid and unsupported `escaped_filter_name_operand_count=1`, `unsupported_filter_count=1`, `unsupported_filter_operand_policy="reject_unsupported_filter_names"`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PDF tokenizer, stream-filter decoder, CMap parser, and review-metadata path in `PdfTextExtractor`.

## Non-Overlap

This is additive to the accepted malformed CMap filter, DecodeParms, Crypt, literal operator, post-endcmap, unsupported filter, and stream owner boundary slices. It specifically records escaped filter-name operands and verifies supported versus unsupported escaped filter behavior on the current accepted base.

Root harness: not run - isolated micro-slice.
