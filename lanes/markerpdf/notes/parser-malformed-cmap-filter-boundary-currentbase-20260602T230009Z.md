# markerPDF malformed CMap filter boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260602T230009Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF text extraction through `pdftext`/PDFium before Markdown and WordPress-visible paragraphs are built. Relevant PDF parser behavior for this slice: `/ToUnicode` and `/Encoding` CMap data are ordinary stream objects, so `/Filter` operands must be valid PDF filter names. Malformed filter operands such as dictionaries inside `/Filter` arrays are not decoders; the CMap stream must fail closed and fallback font decoding must not import raw CMap payload text.

## Behavior

`PdfTextExtractor::extractCMapStreamFilterLengthOwnerReview()` now exposes CMap-specific malformed filter operand metadata:

- `invalid_filter_operand_count`
- `dictionary_filter_operand_count`
- per-entry `decodeparms_resolution_failed`
- per-entry `filter_operand_policy`

The focused fixture builds a Type0 font with `/Encoding /Identity-H` and a `/ToUnicode` stream whose `/Filter` array contains a dictionary followed by `/FlateDecode`. If the malformed dictionary were ignored, the compressed CMap would map text to `Decoded CMap Leak` and `Dictionary Filter Leak`. The native boundary rejects the malformed filter operand, records `reject_dictionary_filter_operands`, and falls back to the Identity-H source bytes so WordPress receives only `Safe Import`.

## Evidence

Focused green after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction

1 test files, 40 assertions, 0 failures
```

Adjacent parser/font gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)

5 test files, 708 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

Required checks:

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

This does not repeat accepted malformed `/FlateDecode` CMap fallback, CMap comment stripping, declared CMap row-count boundaries, `usecmap` inheritance, Type0 Encoding CMap width grouping, indirect `/Length`/`/Filter` owner selection, inline JPX CMap repair, generic content-stream filter-array dictionary rejection, or xref/object-stream filter owner boundaries.

The bounded behavior is specifically CMap stream review and extraction behavior when a CMap `/Filter` array itself contains a dictionary operand before a valid filter name.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, stream dictionary parser, stream filter dispatcher, CMap parser, Type0 Identity-H fallback decoder, text extraction path, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
