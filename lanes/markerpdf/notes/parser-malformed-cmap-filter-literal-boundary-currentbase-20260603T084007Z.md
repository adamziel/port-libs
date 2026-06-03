# markerPDF malformed CMap literal filter boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260603T084007Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `pdftext`/PDFium before Markdown and WordPress-visible paragraphs are produced. The native PHP boundary for this no-GPU lane treats `/ToUnicode` and `/Encoding` CMap data as ordinary PDF streams: every `/Filter` array item must be a filter name or `null`. Literal strings, numbers, nested arrays, dictionaries, booleans, and other direct PDF tokens are not decoders.

## Behavior

The accepted 2026-06-02 CMap filter slice already rejected dictionary operands inside CMap `/Filter` arrays. This isolated extension keeps extraction fail-closed for direct literal operands and adds review metadata so WordPress import tooling can distinguish malformed direct operands from generic unresolved filter operands:

- direct filter operands now include `token_type` and `valid_filter_operand`;
- CMap and object-stream reviews include `malformed_filter_operand_count`;
- CMap review reports `reject_malformed_filter_operands` for direct literal operands such as `(literal filter is not a decoder)`;
- visible WordPress text falls back through `/Encoding /Identity-H`, while decoded CMap payload text and literal filter text stay excluded.

The WordPress smoke now covers both dictionary and literal malformed CMap filter operands, emitting `Safe Import` and `Literal Safe Import` as clean Gutenberg paragraphs.

## Evidence

Red baseline after adding the literal CMap fixture, before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
FAIL classifies literal CMap Filter operands as malformed before current-base text extraction (lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 56 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction

1 test files, 79 assertions, 0 failures
```

Adjacent parser/font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)

8 test files, 827 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

Smoke metadata included:

- `fallback_text="Safe Import | Literal Safe Import"`
- `dictionary_filter_operand_policy="reject_dictionary_filter_operands"`
- `literal_invalid_filter_operand_count=1`
- `literal_malformed_filter_operand_count=1`
- `literal_filter_operand_policy="reject_malformed_filter_operands"`
- `leaking_cmap_text_excluded=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Required checks passed:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
jq empty lanes/markerpdf/lane-status.json
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `/FlateDecode` CMap fallback, dictionary operands inside CMap `/Filter` arrays, CMap comment stripping, declared CMap row-count boundaries, `usecmap` inheritance, Type0 Encoding CMap width grouping, indirect `/Length`/`/Filter` owner selection, inline JPX CMap repair, generic content-stream filter-array dictionary rejection, or xref/object-stream filter owner boundaries.

The bounded behavior is specifically review classification for non-dictionary direct malformed CMap filter operands, proven with a literal string operand before a valid `/FlateDecode` name.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, stream dictionary parser, stream filter dispatcher, CMap parser, Type0 Identity-H fallback decoder, text extraction path, and WordPress smoke renderer.

Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers. Those GPU/model/OCR paths were not run.
