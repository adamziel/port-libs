# markerPDF malformed CMap filter generation boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260604T234845Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reaches searchable PDF text through `marker/pdf/extract_text.py` and pdftext/PDFium font decoding before Markdown/WordPress paragraphs are built. The native no-GPU PHP lane owns the lower PDF parser boundary: a ToUnicode CMap stream's `/Filter` value can be indirect, but the referenced generation selected by the current xref table must be used before deciding whether a CMap stream may decode.

## Behavior

This slice adds focused current-base coverage for an indirect CMap `/Filter 7 1 R` where stale generation `7 0 obj /FlateDecode` is valid, but the xref-selected current generation `7 1 obj << ... >>` is a dictionary-shaped non-decoder. The native parser rejects the current malformed helper instead of falling back to the stale valid name, keeps the compressed ToUnicode CMap undecoded, and falls back to Identity-H source text for WordPress import.

Review metadata for the generation-boundary fixture reports:

- `filter_operands[0].generation=1`
- `filter_operands[0].owner_policy=xref_selected_direct_object`
- `dictionary_filter_operand=true`
- `filter_operand_policy=reject_dictionary_filter_operands`
- `decoded_cmap_count=0`

## Evidence

Baseline before this additive probe on current base:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)

2 test files, 217 assertions, 0 failures
```

Focused green after adding the generation-owner probe:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect CMap Filter arrays with dictionary operands before current-base text extraction
PASS rejects current-generation indirect CMap Filter dictionaries instead of stale valid filters

1 test files, 231 assertions, 0 failures
```

Adjacent parser/font/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 7 selected test files (root lock skipped)

7 test files, 984 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
fallback_text="Safe Import | Literal Safe Import | Indirect Literal Safe Import | Indirect Array Safe Import | Generation Safe Import"
generation_filter_object_generation=1
generation_filter_operand_policy="reject_dictionary_filter_operands"
generation_stale_valid_filter_rejected=true
leaking_cmap_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required checks for this handoff:

```text
php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct dictionary CMap filter operands, direct literal CMap filter operands, selected indirect literal CMap filter operands, selected indirect CMap filter arrays with dictionary operands, indirect CMap filter/length owner selection, object-stream dictionary filter rejection, xref-stream filter/length review, generic stream-filter stack boundaries, inline image filter-array boundaries, XMP packet boundary work, or metadata extraction.

The bounded behavior here is generation-owner selection for indirect ToUnicode CMap `/Filter` operands before CMap decoding and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, xref owner selection, stream filter resolver, CMap parser, Identity-H fallback decoder, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.

## Next Task

Continue with non-overlapping no-GPU markerPDF parser/converter boundaries: remaining font/CMap width edges, stream-filter owner recovery, annotations/forms/security review metadata, image/filter metadata, xref repair, page geometry, and supplied-boundary table/equation handoffs.
