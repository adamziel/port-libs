# markerPDF malformed CMap filter boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T002606Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, with low-level ToUnicode CMap stream decoding delegated to pdftext/PDFium before WordPress-visible Markdown is assembled.

The native no-GPU PHP lane owns the parser boundary before that model-free text import path. A ToUnicode CMap stream whose `/Filter` is valid but whose current xref-selected `/DecodeParms` parameters are malformed must fail closed before compressed CMap mappings can replace fallback source text.

## Behavior

`PdfTextExtractor::extractCMapStreamFilterLengthOwnerReview()` now reports CMap stream `/DecodeParms` operand and parameter state alongside existing `/Filter` review metadata:

- `invalid_decodeparms_operand_count`
- `malformed_decodeparms_operand_count`
- `invalid_decodeparms_parameter_count`
- per-entry `decodeparms_operand_policy`
- per-operand `valid_decodeparms_operand`

The focused fixture uses a Type0 Identity-H fallback text stream plus a compressed ToUnicode CMap payload that would map the first source code to `DecodeParms CMap Leak`. The CMap stream references `/DecodeParms 8 1 R`; xref selects generation `8 1` with `<< /Predictor /Twelve /Columns 1 >>`, while stale generation `8 0` has valid `<< /Predictor 1 >>`.

The current-generation DecodeParms dictionary is syntactically a valid DecodeParms operand, but its `Predictor` value is not an integer, so the CMap stream fails closed with:

- `decodeparms_operand_policy=reject_malformed_decodeparms_parameters`
- `invalid_decodeparms_parameter_count=1`
- `decodeparms_object_generation=1`
- `decoded_cmap_count=0`

WordPress-visible text remains `DecodeParms Safe Import`, and leaking CMap payload text, stale valid DecodeParms, and raw CMap bytes stay out of paragraphs.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect CMap Filter arrays with dictionary operands before current-base text extraction
PASS rejects current-generation indirect CMap Filter dictionaries instead of stale valid filters
PASS rejects current-generation malformed CMap DecodeParms parameters before ToUnicode decoding

1 test files, 286 assertions, 0 failures
```

Adjacent filter/CMap owner family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS reviews filtered ToUnicode CMap stream Length and Filter owners before current-base text extraction
PASS keeps indirect Filter and DecodeParms helpers bound to the current stream owner
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect CMap Filter arrays with dictionary operands before current-base text extraction
PASS rejects current-generation indirect CMap Filter dictionaries instead of stale valid filters
PASS rejects current-generation malformed CMap DecodeParms parameters before ToUnicode decoding
PASS rejects current-generation dictionary Filter operands on object streams before WordPress extraction
PASS rejects stream-owned fake DecodeParms objects before current-base WordPress text extraction
PASS reviews xref-stream indirect Filter and Length owners before current-base WordPress text extraction

6 test files, 410 assertions, 0 failures
```

Broader CMap/DecodeParms family:

```text
php tools/run-tests.php lanes/markerpdf/tests/Pdf*CMap*Test.php lanes/markerpdf/tests/PdfParser*DecodeParms*Test.php
Focused test run: 20 selected test files (root lock skipped)
...
20 test files, 529 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
fallback_text="Safe Import | Literal Safe Import | Indirect Literal Safe Import | Indirect Array Safe Import | Generation Safe Import | DecodeParms Safe Import"
decodeparms_operand_policy="reject_malformed_decodeparms_parameters"
decodeparms_invalid_parameter_count=1
decodeparms_object_generation=1
decodeparms_stale_valid_parameters_rejected=true
leaking_cmap_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted direct dictionary/literal malformed CMap `/Filter` boundary, selected indirect non-name CMap `/Filter` operand classification, current-generation dictionary filter selection, CMap `/Filter` and `/Length` owner review, xref-stream filter review, object-stream filter dictionary generation review, generic stream DecodeParms owner repair, stream-filter stack fail-closed behavior, CMap width grouping, malformed ToUnicode fallback, or encrypted-PDF preflight.

The bounded behavior here is specifically current xref-selected malformed CMap `/DecodeParms` parameter classification before ToUnicode CMap decoding and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, xref owner selection, stream filter resolver, DecodeParms validator, ToUnicode CMap parser, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
