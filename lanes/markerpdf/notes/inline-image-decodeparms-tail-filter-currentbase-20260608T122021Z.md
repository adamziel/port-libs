# markerPDF Inline Image DecodeParms Tail Filter Boundary

## Scope

This isolated markerPDF slice covers renderer-side inline image dictionaries where a direct `/DecodeParms` dictionary is followed by a bounded malformed tail operand before later `/Filter` and `/Decode` keys:

```pdf
BI /W 3 /H 1 /CS /G /BPC 8
   /DP << /Predictor 12 /Columns 3 /Colors 1 /BitsPerComponent 8 >> 99 0 R
   /F /Fl /D [1 0] ID ...
EI
```

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image extraction and review. For this no-GPU PHP port, inline image payload bytes must not leak into WordPress paragraphs, malformed image decode metadata must fail closed before native preview, and recoverable filter/decode metadata should remain visible for importer review.

## Behavior

`PdfImageRenderer` now resumes PDF dictionary scanning after bounded malformed `/DecodeParms` tail operands. The malformed tail marks the inline image dictionary review-only and native preview fails closed, but later `/Filter /Fl` and `/Decode [1 0]` metadata are preserved. Native DecodeParms review still reports the safe prefix fields and adds `decode_parms_operand` as the invalid field.

This does not repeat pending scalar-null `/Filter` extra decoder-name handling, duplicate `/Filter` declarations, multi-tail `/Decode` recovery, DCT tail DecodeParms handling, CCITT Fax tail classification, OCR, Surya/Texify/Torch, pypdfium/PIL, or external PDF tooling.

## Changed Files

- `lanes/markerpdf/src/PdfImageRenderer.php`
- `lanes/markerpdf/tests/PdfInlineImageDecodeParmsTailFilterBoundaryCurrentBaseTest.php`
- `lanes/markerpdf/examples/wordpress-pdf-inline-image-decodeparms-tail-filter-currentbase.php`
- `lanes/markerpdf/lane-status.json`
- `lanes/markerpdf/notes/inline-image-decodeparms-tail-filter-currentbase-20260608T122021Z.md`

## Red First

Before the source fix, the focused test failed because image filter metadata stopped at the malformed `/DecodeParms` tail and never reached the later `/Filter /Fl` key.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeParmsTailFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves inline Filter and Decode metadata after malformed DecodeParms tail before WordPress extraction
Values are not identical
Expected: array (0 => 'FlateDecode')
Actual: array ()
1 test files, 8 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeParmsTailFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves inline Filter and Decode metadata after malformed DecodeParms tail before WordPress extraction
1 test files, 22 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeParmsTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeTailDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsTrailingOperandCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1079 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decodeparms-tail-filter-currentbase.php
exits 0 with filter_preserved_after_decodeparms_tail=true, decode_preserved_after_decodeparms_tail=true, preview_failed_closed=true, inline_payload_excluded_from_text=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, inline image review path, native filter DecodeParms metadata, focused PHP test harness, and WordPress smoke pattern. Live OCR/model execution and exact upstream raster/model parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Next Task

Continue with a non-overlapping native PDF parser/converter boundary: fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
