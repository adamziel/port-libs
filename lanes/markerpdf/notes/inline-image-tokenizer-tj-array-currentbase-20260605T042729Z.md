# markerPDF Inline Image Tokenizer TJ Array Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T042729Z`

Base accepted HEAD: `3d3f92ca9efec57a02140096ea7b4622458fe97e`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through parser-backed extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, while text arrays after the real image terminator remain visible document text.

The native no-GPU tokenizer already handled malformed `BI` preambles, early `EI` image payload bytes, preview-only DCT/JPX/JBIG2/CCITT filters, unsupported filters, named color spaces, visible `EI` inside a plain `Tj` literal after fallback close, and slash-delimited `EI/name` operator boundaries. This slice adds the same resumed-text boundary for `TJ` array literals.

## Red First

A one-off current-base probe used a preview-only `/JBIG2Decode` inline image followed by visible `TJ` array text:

```text
BT /F1 12 Tf 72 720 Td (Before TJ Array EI Text) Tj ET
BI /W 128 /H 1 /IM true /F /JBIG2Decode ID
\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (TJ Array Payload EI Noise) Tj ET rawtail
EI
BT /F1 12 Tf 72 704 Td [(Visible EI Array Text)] TJ ET
```

Before the patch, `PdfTextExtractor::extractTextLines()` returned only `['Before TJ Array EI Text']`, because the fallback scanner skipped the truncated `TJ` array segment opaquely and kept searching past the real inline-image terminator.

## Implementation

`PdfTextExtractor::contentSegmentContainsUnterminatedTextLiteralAfterTextObject()` now scans arrays while inside a resumed text object and reports an unterminated literal before the next raw `EI` candidate. The change is limited to the existing safe preview-only fallback decision in `skipInlineImage()`; ordinary inline-image dictionary parsing, exact sample-floor validation, and preview-filter framing are unchanged.

## Verification

Focused tokenizer run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 144 assertions, 0 failures
```

Adjacent inline-image/text extraction sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1670 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `preview_only_visible_ei_tj_array_text_preserved_after_safe_boundary=true`, renders `Visible EI Array Text`, excludes `TJ Array Payload EI Noise`, and reports `executes_python_or_models=false` plus `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered sample-length `EI` validation, slash-delimited inline dictionaries, nested modifier-dictionary decoys, ASCII85/Flate/LZW/RunLength DecodeParms validation, DCT/JPX/JBIG2/CCITT preview-only framing, wrapped preview-filter chains, raw JBIG2 segments, direct CCITT EOFB/RTC stream boundaries, unsupported `/Crypt` filter boundaries, named ColorSpace fallback, plain visible `EI` literal recovery, slash-delimited `EI/name` closure, object-stream inline-image repair, image XObject payload exclusion, or stream-owner `endstream` decoy recovery.

The bounded behavior is specifically resumed `TJ` text-array literals containing standalone `EI` bytes after a preview-only inline image fallback boundary.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, PDF array/literal tokenizer, preview-only filter boundaries, `PdfTextExtractor`, focused lane tests, and the WordPress smoke path. Full upstream parity remains intentionally gated on live pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
