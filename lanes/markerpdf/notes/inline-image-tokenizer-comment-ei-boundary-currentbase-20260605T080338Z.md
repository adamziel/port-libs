# markerPDF Inline Image Tokenizer Comment EI Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T080338Z`

Base accepted HEAD: `056c480e2f65f95e3b18ca84727b1326e5c155ba`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through `marker/pdf/extract_text.py` into parser/PDFium-backed extraction before image, OCR, and model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, while PDF comments after the real image terminator are lexical comments and must not become WordPress paragraph text.

This no-GPU native slice covers the adjacent tokenizer case where a preview-only inline image has already reached a safe fallback terminator, then a following PDF comment contains delimiter-looking `EI` and text-looking operator bytes.

## Red First

A one-off current-base probe before the source edit returned comment text as visible content:

```text
array (
  0 => 'Before Comment EI Boundary',
  1 => 'Comment After Inline Noise',
  2 => 'After Comment EI Boundary',
)
```

The fixture used a `/JBIG2Decode` inline ImageMask payload with an early fake `EI`, the real inline-image terminator, and then:

```text
% comment EI BT /F1 12 Tf 72 640 Td (Comment After Inline Noise) Tj ET
BT /F1 12 Tf 72 704 Td (After Comment EI Boundary) Tj ET
```

## Implementation

`PdfTextExtractor::skipInlineImage()` now keeps the existing safe preview-only fallback boundary when a later raw `EI` candidate is lexically inside a PDF comment. The new scanner is token-aware for strings, hex strings, dictionaries, arrays, names, and comments, so `EI` in a real comment line cannot replace the prior inline-image fallback, but existing visible literal, `TJ` array, marked-content replacement, and next-inline-image recovery paths still work.

The WordPress smoke now includes the same post-terminator comment boundary and emits `post_inline_image_comment_ei_excluded=true`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 189 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 1747 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emitted `post_inline_image_comment_ei_excluded=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID` or tight `EI` sample-floor recovery, immediate comments after `ID`, compact slash-delimited dictionaries, nested dictionary decoys, JBIG2/raw-JBIG2/CCITT/unsupported-filter open-ended payload boundaries, visible literal `EI`, `TJ` array `EI`, marked-content `/ActualText` `EI`, named color spaces, slash-delimited `EI/name`, ASCIIHex/RunLength/Flate/LZW decode boundaries, or inline image review metadata.

The bounded behavior is specifically post-terminator PDF comments whose comment text contains raw `EI` and text-looking operators after a preview-only inline image fallback.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, PDF comment scanner, preview-only inline image fallback state, `PdfTextExtractor`, and WordPress smoke path. Full upstream parity remains dependency-gated on live `pdftext`, `pypdfium2`/PDFium, Surya/Torch model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
