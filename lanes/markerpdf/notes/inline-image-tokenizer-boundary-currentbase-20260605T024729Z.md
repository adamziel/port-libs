# markerPDF Inline Image Tokenizer Visible EI Text Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T024729Z`

Base accepted HEAD: `93ff2a1225d594c3864b3222b381965462c18bba`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, while text objects after the real image terminator remain visible document text.

The native no-GPU tokenizer already handles unfiltered sample floors, slash-delimited dictionaries, nested dictionary decoys, JPX/JBIG2/DCT/CCITT preview-only filters, unsupported filters, and native filter end markers. This slice covers the current-base edge where an open-ended preview-only inline image reaches its safe fallback terminator, then resumed visible text contains standalone `EI` bytes inside a literal string.

## Red First

A one-off `PdfTextExtractor` probe on the current base used this content stream:

```text
BT /F1 12 Tf 72 720 Td (Before Visible EI Text) Tj ET
BI /W 128 /H 1 /IM true /F /JBIG2Decode ID
\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Preview Payload EI Noise) Tj ET rawtail
EI
BT /F1 12 Tf 72 704 Td (Visible EI Marker Text) Tj ET
```

Before the fix, extraction returned only `['Before Visible EI Text']`; the scanner kept searching after the real inline-image terminator and treated `EI` inside the visible literal as a later image fallback boundary.

## Implementation

`PdfTextExtractor::skipInlineImage()` now closes a safe open-ended preview fallback before resumed text-object literal content. The new helper scans the segment after the candidate fallback with normal PDF token boundaries, tracks `BT`/`ET`, and only closes early when the next raw `EI` candidate is inside an unterminated text literal after text parsing has resumed.

That keeps the existing conservative behavior for fake `EI BT ...` bytes inside image payloads, while preserving visible WordPress paragraph text such as `Visible EI Marker Text` after the real `EI` terminator.

## Verification

Focused tokenizer test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 117 assertions, 0 failures
```

Adjacent inline-image/text extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1591 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `preview_only_visible_ei_text_preserved_after_safe_boundary=true`, `real_inline_image_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders the `Visible EI Marker Text` paragraph while excluding `Preview Payload EI Noise`.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered sample-length `EI` validation, slash-delimited inline dictionaries, nested modifier-dictionary decoys, ASCII85/Flate/LZW/RunLength DecodeParms validation, DCT segment-aware EOI handling, JPX/JBIG2/CCITT preview-only framing, wrapped preview-filter chains, raw JBIG2 segments, direct CCITT EOFB/RTC stream boundaries, unsupported `/Crypt` filter boundaries, object-stream inline-image repair, or image XObject payload exclusion.

The new behavior is specifically resumed text-object literal recovery after a safe preview-only inline-image fallback, where visible text itself contains standalone `EI`.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, PDF literal tokenizer, preview-only filter boundaries, focused lane tests, and the WordPress smoke path. Full upstream parity remains intentionally gated on live pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
