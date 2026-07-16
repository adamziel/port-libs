# markerPDF malformed CMap filter stale-reference boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T005422Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, with low-level ToUnicode CMap stream decoding delegated to pdftext/PDFium before Markdown/WordPress paragraphs are assembled.

The native no-GPU PHP lane owns the lower parser boundary. A ToUnicode CMap stream `/Filter` reference can name a stale generation such as `7 0 R`, but if the current xref row selects `7 1 obj` as a malformed dictionary, the parser review must report the selected current owner and fail closed instead of previewing or decoding a stale valid `/FlateDecode` helper.

## Behavior

`PdfTextExtractor::extractCMapStreamFilterLengthOwnerReview()` now records `selected_generation` for indirect stream operands and classifies mismatched-generation filter references using the xref-selected object body.

The focused fixture uses `/Filter 7 0 R` with:

- stale `7 0 obj /FlateDecode`
- current xref-selected `7 1 obj << /Owner (xref-selected dictionary is not a decoder) ... >>`
- a compressed ToUnicode CMap payload that would map source text to `Stale Reference CMap Leak`

The native parser keeps visible text as `Stale Reference Safe Import`, reports `filter_operand_policy=reject_dictionary_filter_operands`, `owner_policy=unresolved_or_unselected_indirect_operands`, `filter_operands[0].generation=0`, `filter_operands[0].selected_generation=1`, and `decoded_cmap_count=0`.

## Evidence

Baseline focused test before this additive case on accepted base `c39e6ef5dc53ab6c10abe3cd85218cbaaa83096e`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 286 assertions, 0 failures
```

Focused green after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect CMap Filter arrays with dictionary operands before current-base text extraction
PASS rejects current-generation indirect CMap Filter dictionaries instead of stale valid filters
PASS rejects current-generation malformed CMap DecodeParms parameters before ToUnicode decoding
PASS classifies stale-generation CMap Filter references by the current xref-selected malformed owner

1 test files, 341 assertions, 0 failures
```

Adjacent parser/filter/CMap family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
...
9 test files, 1115 assertions, 0 failures
```

Broad CMap/filter/DecodeParms glob:

```text
php tools/run-tests.php lanes/markerpdf/tests/Pdf*CMap*Test.php lanes/markerpdf/tests/PdfParser*DecodeParms*Test.php lanes/markerpdf/tests/PdfParser*Filter*Test.php
Focused test run: 34 selected test files (root lock skipped)
...
34 test files, 855 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
stale_reference_valid_filter_rejected=true
stale_reference_selected_generation=1
stale_reference_current_dictionary_classified=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required local checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted direct dictionary/literal malformed CMap `/Filter` boundary, selected indirect non-name CMap `/Filter` operand classification, selected current-generation dictionary `/Filter 7 1 R` boundary, malformed CMap `/DecodeParms` parameter boundary, CMap `/Filter` and `/Length` owner review, xref-stream filter review, object-stream filter dictionary generation review, generic stream DecodeParms owner repair, stream-filter stack fail-closed behavior, CMap width grouping, malformed ToUnicode fallback, or encrypted-PDF preflight.

The bounded behavior here is specifically stale-generation ToUnicode CMap `/Filter 7 0 R` review when the current xref-selected owner is a different malformed dictionary generation.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, xref owner selection, stream filter resolver, CMap parser, Identity-H fallback decoder, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.

## Next Task

Continue with non-overlapping no-GPU markerPDF parser/converter boundaries: remaining font/CMap width edges, stream-filter owner recovery, annotations/forms/security review metadata, image/filter metadata, xref repair, page geometry, and supplied-boundary table/equation handoffs.
