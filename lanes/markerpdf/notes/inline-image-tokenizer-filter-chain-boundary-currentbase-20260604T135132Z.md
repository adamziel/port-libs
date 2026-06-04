# markerPDF Inline Image Tokenizer Filter-Chain Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260604T135132Z`

Base accepted HEAD: `bba93955f76fb963b095a69b8a7b6c2b36774a64`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates page text extraction through `marker/pdf/extract_text.py` to `pdftext.extraction.dictionary_output(...)` and pypdfium/PDFium text pages. At that boundary, `BI ... ID ... EI` inline image data is raster payload, not visible text for WordPress paragraphs.

The native no-GPU PHP tokenizer already handled raw JPX/DCT/JBIG2 inline image boundaries and DCT wrapped by an earlier verifiable filter. This slice maps the same boundary for JPX/JBIG2 payloads wrapped by native decodable filters such as RunLength, ASCIIHex, ASCII85, LZW, or Flate before the preview-only raster filter.

## Implementation

`PdfTextExtractor` now checks JPXDecode and JBIG2Decode candidate state through `inlineImageBytesBeforePreviewFilter(...)`, matching the existing DCTDecode path. This lets the tokenizer decode verifiable filters up to the preview-only raster filter before deciding whether delimiter-looking `EI` bytes are inside image payload data or a safe inline-image terminator.

The focused fixture uses:

- `BI /F [/RL /JPXDecode] ... ID ... EI` where decoded JPX bytes include `EI BT ... Tj` before the JPX end marker.
- `BI /F [/RunLengthDecode /JBIG2Decode] ... ID ... EI` where decoded JBIG2 bytes include `EI BT ... Tj` before the final preview-only fallback boundary.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
PASS keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied
PASS keeps JBIG2 preview-only inline image payload closed across delimiter-looking EI bytes
FAIL keeps preview-only inline image filter chains closed before WordPress text extraction (lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Before Wrapped Preview Filter',
  1 => 'Between Wrapped Preview Filters',
  2 => 'After Wrapped Preview Filters',
)
Actual: array (
  0 => 'Before Wrapped Preview Filter',
)

1 test files, 28 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps malformed BI tokenizer boundary from swallowing later WordPress text
PASS keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied
PASS keeps JBIG2 preview-only inline image payload closed across delimiter-looking EI bytes
PASS keeps preview-only inline image filter chains closed before WordPress text extraction

1 test files, 37 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 842 assertions, 0 failures
```

## WordPress Smoke

`wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php` now emits `wrapped_preview_filter_chain_text_preserved=true` and `wrapped_preview_filter_payload_excluded=true` while rendering only visible text paragraphs. It does not execute Python, PDFium, pypdfium, PIL, OCR/model code, or external PDF tools.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered inline image sample-length `EI` validation, Flate `DecodeParms` validation, null filter-array alignment, raw JPX/DCT/JBIG2 inline framing, inline ImageMask preview rows, image XObject JBIG2/CCITT payload exclusion, object-stream inline-image repair, or stream-filter fail-closed behavior.

The new behavior is specifically filter-chain-aware tokenizer recovery for preview-only JPX/JBIG2 inline images after native decodable filters.

## Dependency Closure

No new support component is needed. The slice reuses the native content tokenizer, inline-image dictionary parser, stream filter metadata parser, native RunLength/ASCIIHex/ASCII85/LZW/Flate decoders, preview-only JPX/JBIG2 review boundaries, `PdfTextExtractor`, and WordPress smoke path. Full upstream parity remains gated on live `pdftext`, `pypdfium2`/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
