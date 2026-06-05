# markerPDF Inline Image Decode Terminal Whitespace Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T050854Z`

Base accepted HEAD: `a507c91dfef9ccb6ae9e0ed8b5624323759e56e8`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through parser-backed PDF text extraction before image rendering, OCR, and model stages. At that boundary, `BI ... ID ... EI` inline-image bytes are raster payload, not WordPress paragraph text.

PDF inline image data can contain arbitrary sample bytes. When a declared unfiltered image sample byte is itself whitespace immediately before `EI`, the tokenizer must use the declared image sample floor instead of trimming that byte as delimiter-only whitespace.

## Red First

The current base swallowed all text after both focused fixtures:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps terminal whitespace samples inside unfiltered inline images before text extraction
Expected: ['Before Space Sample Inline Image', 'After Space Sample Inline Image']
Actual:   ['Before Space Sample Inline Image']
FAIL keeps terminal whitespace samples inside named-colorspace inline image floors
Expected: ['Before Named Space Sample Inline Image', 'After Named Space Sample Inline Image']
Actual:   ['Before Named Space Sample Inline Image']

1 test files, 159 assertions, 2 failures
```

## Implementation

`PdfTextExtractor::skipInlineImage()` now checks a raw candidate only when:

- the inline image has no filters;
- the normal trimmed candidate falls below the declared sample floor;
- the raw bytes before `EI` exactly reach the expected decoded sample floor or named-color-space minimum floor.

This preserves the existing delimiter trimming for filtered images and ordinary unfiltered images, while allowing a terminal whitespace sample byte to close the inline image before later WordPress text is parsed.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 171 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1684 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke emits `terminal_whitespace_inline_sample_preserved=true`, `named_colorspace_terminal_whitespace_sample_preserved=true`, `visible_text_imported=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered non-whitespace sample-length `EI` validation, slash-delimited inline dictionaries, nested dictionary decoys, ASCII85/Flate/LZW/RunLength DecodeParms validation, filtered sample-floor acceptance, DCT/JPX/JBIG2/CCITT preview-only framing, inline ImageMask/Indexed/ColorKey preview metadata, unsupported `/Crypt` tokenizer boundaries, object-stream inline-image repair, image XObject payload exclusion, DCT null-filter boundaries, or stream-filter stack recovery.

The bounded behavior is specifically unfiltered inline images whose terminal declared sample byte is whitespace and would otherwise be trimmed before matching `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, declared sample-size calculator, `PdfTextExtractor`, focused lane tests, and the WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers; none were run for this native parser slice.
