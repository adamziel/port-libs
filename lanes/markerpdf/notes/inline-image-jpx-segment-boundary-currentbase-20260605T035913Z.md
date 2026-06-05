# markerPDF Inline JPX Segment Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T035913Z`

Base accepted HEAD: `2322d9a373b0f7fab5e3d6b939f4dd65c45c544f`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser/PDFium-backed text extraction before image rendering, OCR, and model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, not visible WordPress paragraph text.

For native no-GPU parsing, `/JPXDecode` remains review-only, but raw JPEG 2000 codestream framing is still useful for the tokenizer: an `FF D9` EOC marker inside a length-coded marker segment is data, not the image boundary.

## Red First

The current base leaked the text payload after a false EOC inside a SIZ marker segment:

```text
array (
  0 => 'Before inline JPX segment',
  1 => 'Inline JPX segment leak',
  2 => 'After inline JPX segment',
)
```

## Implementation

`PdfTextExtractor::inlineJpxCandidateState()` now asks a small JPEG 2000 codestream walker whether a structured raw JPX payload is complete. The walker starts after `SOC` (`FF 4F`), skips length-coded marker segments using their declared segment lengths, treats short segment candidates as incomplete, and only accepts `EOC` (`FF D9`) when it appears at a marker boundary. Unstructured legacy JPX fixtures still fall back to the older raw `FF D9` heuristic.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 157 assertions, 0 failures
```

Adjacent inline image/JPX/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 1582 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-jpx-segment-boundary-currentbase.php
```

The smoke emits `false_jpx_eoc_inside_length_segment_ignored=true`, `inline_jpx_payload_excluded_from_text=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline-image raw `BI/ID/EI` exclusion, unfiltered sample-floor validation, ASCII85/Flate/LZW/RunLength DecodeParms validation, filtered oversized sample-floor acceptance, DCT segment-aware EOI handling, JBIG2/CCITT/unsupported preview-only fallbacks, inline ImageMask/Indexed/ColorKey preview metadata, object-stream inline-image repair, or image XObject payload exclusion.

The bounded behavior is specifically inline `/JPXDecode` tokenizer closure when a false `FF D9` EOC appears inside a length-coded JPEG 2000 codestream marker segment before a fake `EI` token.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, JPX review-only filter boundary, and WordPress smoke path. Full JPEG 2000 raster decoding remains gated on a future native raster backend or the upstream pypdfium2/PDFium path, and OCR/model execution remains intentionally out of scope under the current markerPDF no-GPU directive.
