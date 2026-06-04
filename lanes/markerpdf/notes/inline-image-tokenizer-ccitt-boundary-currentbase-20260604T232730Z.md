# markerPDF Inline Image Tokenizer CCITT Boundary Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260604T232730Z`

Base accepted HEAD: `4e5b254a36b80b692f93413b376a79f6d854dcc7`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text extraction through `marker/pdf/extract_text.py`, where searchable text comes from `pdftext.extraction.dictionary_output(...)` while image rendering is handled through PDF raster/image paths.

At that dependency boundary, `BI ... ID ... EI` inline image bytes are raster payload, not visible WordPress paragraph text. CCITTFax image data is also preview-only in this native no-GPU PHP lane, matching the existing CCITT Image XObject boundary.

## Implementation

`PdfTextExtractor` now treats inline image filters `/CCITTFaxDecode` and `/CCF` as preview-only tokenizer boundaries. Delimiter-looking `EI` bytes inside CCITT payloads no longer reopen text-token parsing and leak fake `BT ... Tj` payload text into WordPress paragraphs.

The focused tests add one `/CCITTFaxDecode` inline ImageMask fixture and one `/CCF` abbreviation fixture. Both keep the before/after searchable text and exclude payload noise plus raw trailing bytes.

## Red First

Focused red probe before the source edit:

```text
array (
  0 => 'Before CCITT Inline',
  1 => 'CCITT Inline Payload Noise',
  2 => 'After CCITT Inline',
)
Before CCITT Inline
CCITT Inline Payload Noise
After CCITT Inline
```

An intermediate two-CCITT-images fixture exposed a broader pre-existing fallback limitation where multiple preview-only inline filters without complete raster markers in the same content stream can swallow text between them. That broader recovery is intentionally left as a follow-up; this slice is bounded to the single inline-image CCITT payload leak.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
PASS keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied
PASS keeps JBIG2 preview-only inline image payload closed across delimiter-looking EI bytes
PASS keeps preview-only inline image filter chains closed before WordPress text extraction
PASS keeps CCITTFax preview-only inline image payload closed across delimiter-looking EI bytes
PASS keeps CCF abbreviated inline image payload closed across delimiter-looking EI bytes

1 test files, 54 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 1392 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emitted `preview_only_ccitt_payload_excluded_until_safe_boundary=true`, `preview_only_jbig2_payload_excluded_until_safe_boundary=true`, `wrapped_preview_filter_chain_text_preserved=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered inline image sample-length `EI` validation, ASCII85/Flate DecodeParms validation, inline JPX/DCT/JBIG2 framing, inline ImageMask preview rows, inline Indexed/JBIG2 review metadata, Image XObject CCITT/JBIG2 payload exclusion, object-stream inline-image repair, or stream-filter fail-closed behavior.

The new bounded behavior is specifically CCITTFaxDecode/CCF inline-image tokenizer recovery before WordPress text extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native content tokenizer, inline-image dictionary parser, stream filter metadata parser, CCITT review-only filter boundary, `PdfTextExtractor`, and WordPress smoke path. Full upstream parity remains dependency-gated on live `pdftext`, `pypdfium2`/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
