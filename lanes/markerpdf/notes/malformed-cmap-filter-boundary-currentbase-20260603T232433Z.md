# markerPDF malformed CMap filter boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260603T232433Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reaches ToUnicode CMap streams through `marker/pdf/extract_text.py` and pdftext/PDFium font decoding. The native no-GPU PHP lane owns the parser boundary before WordPress text import: CMap stream `/Filter` operands must be real PDF filter names or valid name arrays, and malformed operands must not decode mapping payloads into visible text.

## Behavior

`PdfTextExtractor` now validates xref-selected indirect CMap `/Filter` operands with the same token-type policy used for direct operands. Selected indirect literal operands are reported with `token_type=literal`, `valid_filter_operand=false`, `malformed_filter_operand_count=1`, and `filter_operand_policy=reject_malformed_filter_operands`.

The focused fixture keeps the page text safe by using fallback CID decoding while the compressed CMap contains a leaking ToUnicode mapping. The current xref table selects object `7 0 R` as an indirect literal filter operand before `/FlateDecode`; the native extractor now fails CMap decoding closed, excludes the leaking CMap text, and preserves review metadata for the malformed filter boundary.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
FAIL classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction (lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 95 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction

1 test files, 129 assertions, 0 failures
```

Adjacent filter-owner gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
PASS reviews filtered ToUnicode CMap stream Length and Filter owners before current-base text extraction
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction
PASS rejects current-generation dictionary Filter operands on object streams before WordPress extraction
PASS reviews xref-stream indirect Filter and Length owners before current-base WordPress text extraction

4 test files, 232 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
fallback_text="Safe Import | Literal Safe Import | Indirect Literal Safe Import"
dictionary_filter_operand_policy="reject_dictionary_filter_operands"
literal_filter_operand_policy="reject_malformed_filter_operands"
indirect_literal_filter_operand_policy="reject_malformed_filter_operands"
indirect_literal_owner_policy="xref_selected_indirect_operands"
leaking_cmap_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted direct dictionary/literal malformed CMap filter boundary, indirect CMap filter/length owner selection, object-stream dictionary filter rejection, xref-stream filter/length review, inline image filter-array boundaries, generic stream-filter stack boundaries, encrypted metadata preflight, or runtime preflight numeric-gate work.

The bounded behavior here is selected indirect non-name CMap `/Filter` operand classification before ToUnicode CMap decoding and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, xref owner selection, stream filter resolver, ToUnicode CMap review metadata, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
