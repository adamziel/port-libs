# markerPDF malformed CMap filter boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260604T231825Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reaches searchable PDF text through `marker/pdf/extract_text.py` and pdftext/PDFium font decoding before Markdown/WordPress paragraphs are built. The native no-GPU PHP lane owns the lower PDF parser boundary: a ToUnicode CMap stream's `/Filter` value can be a name or an array of names/nulls, but dictionary tokens inside that array are not decoders and must not let compressed CMap payload text become visible import text.

## Behavior

`PdfTextExtractor` now classifies xref-selected indirect CMap `/Filter` array helper objects that contain top-level dictionary items as dictionary filter operands, even when the public review preview is truncated. The CMap stream still fails closed before decoding, fallback Identity-H source text remains visible, and review metadata now reports:

- `dictionary_filter_operand=true` on the selected indirect filter helper operand;
- `dictionary_filter_operand_count=1`;
- `malformed_filter_operand_count=0`;
- `filter_operand_policy=reject_dictionary_filter_operands`.

The focused fixture stores `/Filter 7 0 R`, with object `7 0 R` selected by the xref table as `[ << /Owner (...) /Fake [ /Nested ] >> /FlateDecode ]`. If the invalid dictionary were ignored, the compressed CMap would map glyph bytes to `Indirect Array Dictionary Leak`; the native extractor now keeps that payload and the filter helper text out of WordPress paragraphs.

## Evidence

Red baseline after adding the focused probe:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction
FAIL classifies selected indirect CMap Filter arrays with dictionary operands before current-base text extraction (lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 149 assertions, 1 failures
```

Focused green after the classifier repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect CMap Filter arrays with dictionary operands before current-base text extraction

1 test files, 176 assertions, 0 failures
```

Adjacent parser/font/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 7 selected test files (root lock skipped)

7 test files, 929 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
fallback_text="Safe Import | Literal Safe Import | Indirect Literal Safe Import | Indirect Array Safe Import"
indirect_array_dictionary_dictionary_filter_operand_count=1
indirect_array_dictionary_malformed_filter_operand_count=0
indirect_array_dictionary_filter_operand_policy="reject_dictionary_filter_operands"
indirect_array_dictionary_operand_classified=true
leaking_cmap_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
jq empty lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All passed.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct dictionary CMap filter operands, direct literal CMap filter operands, xref-selected indirect literal CMap filter operands, indirect CMap filter/length owner selection, object-stream dictionary filter rejection, xref-stream filter/length review, generic stream-filter stack boundaries, or inline image filter-array boundaries.

The bounded behavior here is dictionary classification for xref-selected indirect CMap `/Filter` array helper objects before ToUnicode CMap decoding and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, xref owner selection, stream filter resolver, CMap parser, Identity-H fallback decoder, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.
