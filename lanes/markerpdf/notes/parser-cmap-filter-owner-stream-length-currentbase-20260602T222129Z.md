# markerPDF parser CMap filter owner stream length current-base

Micro-slice: `parser-cmap-filter-owner-stream-length-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level font/CMap stream decoding to `pdftext` and PDFium before Markdown/WordPress-visible text is built.

Relevant parser boundary: PDF ToUnicode and Encoding CMap streams are ordinary PDF streams. Their `/Length`, `/Filter`, and `/DecodeParms` operands must resolve through the current xref-selected object owners before the CMap maps source character codes to Unicode. Encrypted PDFs remain fail-closed in this native boundary because no decryption backend is available.

## Behavior

Added `PdfTextExtractor::extractCMapStreamFilterLengthOwnerReview()` for review-only CMap stream operand provenance.

The new focused fixture builds a current-base PDF where:

- page text uses a Type0 font with `/ToUnicode 6 1 R`;
- object `6 0` is a stale CMap generation that maps text to `Stale CMap Leak`;
- object `6 1` is the xref-selected filtered CMap stream;
- the selected CMap stream uses indirect `/Filter 20 1 R` and `/Length 21 1 R`;
- stale helper generations `20 0` and `21 0` are present but not selected;
- the stored Flate CMap bytes contain fake `endstream`, `endobj`, and `99 0 obj` owner tokens;
- the current trailer can be switched to `/Encrypt` and the review path returns no CMap entries.

Visible WordPress text now comes from the current filtered CMap mapping: `Current CMap Owner` and `Length Filter Review`. Stale CMap text, fake stream-owned object headers, stale filter helper names, raw CMap payload bytes, and encrypted CMap streams stay out of visible content.

The review metadata records CMap name, object/generation, reference usage (`to_unicode` / `encoding_cmap`), declared length, filters, operand owner policies, decoded length/hash, and whether all indirect operands were xref-selected. It does not include decoded CMap payload text.

## Evidence

Red baseline before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL reviews filtered ToUnicode CMap stream Length and Filter owners before current-base text extraction
Call to undefined method PortLibs\MarkerPDF\PdfTextExtractor::extractCMapStreamFilterLengthOwnerReview()

1 test files, 0 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reviews filtered ToUnicode CMap stream Length and Filter owners before current-base text extraction

1 test files, 41 assertions, 0 failures
```

Adjacent parser/font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)

8 test files, 711 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-cmap-filter-owner-stream-length-currentbase.php
```

Smoke metadata emitted:

- `uses_current_cmap_owner_text=true`
- `uses_current_length_filter_review_text=true`
- `stale_cmap_generation_excluded=true`
- `stream_owned_fake_object_excluded=true`
- `stale_filter_helper_excluded=true`
- `cmap_owner_policy="xref_selected_indirect_operands"`
- `cmap_name="WPCurrentCMapOwner-H"`
- `decoded_cmap_count=1`
- `encrypted_review_fails_closed=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Required checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-cmap-filter-owner-stream-length-currentbase.php
jq empty lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All passed.

Status delta: behavior tests `895 -> 896` pass / `0` fail.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted xref-stream `/Filter`/`Length` owner review, ordinary content-stream indirect filter/DecodeParms owner repair, inline stream length/filter repair, object-stream filter dictionary generation review, xref object-stream filter-chain recovery, Type0 CMap width grouping, malformed ToUnicode fallback, CMap comments, usecmap inheritance, or encrypted-PDF preflight slices.

The new behavior is specifically the CMap stream operand review and WordPress import boundary where current xref-selected `/Length` and `/Filter` owners must be used before ToUnicode text mapping.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref table/stream selector, stream boundary repair, stream filter decoder, ToUnicode/CID CMap parser, encrypted-PDF fail-closed preflight, and WordPress smoke renderer.

Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
