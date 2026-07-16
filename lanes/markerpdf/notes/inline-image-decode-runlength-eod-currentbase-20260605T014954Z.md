# markerPDF Inline Image Decode RunLength EOD Current Base

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T014954Z`

Session: `port-dev-markerpdf-inline-image-decode-20260605T014954Z`

Base accepted HEAD: `cc7fd13b239c01bb3ecb5d1e841d059e64608127`

## Source Truth

Upstream markerPDF routes searchable PDF text through parser/PDFium-backed text extraction and treats `BI ... ID ... EI` inline image bytes as raster payload, not visible WordPress paragraph text. For the native no-GPU PHP boundary, standard inline image filters that can be decoded natively must fail closed when their own end-of-data boundary is malformed; supplied future raster samples are only a safe escape hatch for preview-only raster filters such as JPX, not for failed native filters.

RunLengthDecode is a standard PDF stream filter whose data must terminate with the EOD byte `0x80`. Delimiter-looking `EI` bytes inside encoded RunLength data remain image payload until the filter boundary is complete.

## Behavior

This patch adds the missing RunLength inline-image decode boundary:

- `PdfImageRenderer::streamFilterInputHasExplicitEndMarker()` now requires the RunLength EOD byte before accepting inline image decode previews that request explicit filter boundaries.
- `PdfImageRenderer::inlineImageColorSpaceMaskOutputPreviewRows()` no longer lets supplied samples bypass a failed native inline image decoder. Supplied samples remain allowed for review-only raster filters.
- `PdfInlineImageDecodeBoundaryCurrentBaseTest.php` now proves RunLength payload text with `EI` bytes is excluded from extracted WordPress text, complete RunLength inline Indexed samples decode into palette preview rows, and missing RunLength EOD cannot be rescued by supplied RGB samples.
- The existing WordPress smoke now emits RunLength EOD and fail-closed supplied-sample metadata while rendering only clean Gutenberg paragraph text.

## Red First

With the new focused case added before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS requires ASCII85 inline image end marker before accepting delimiter-looking EI bytes
PASS requires ASCII85 inline image review payload terminator before RGB preview decoding
PASS decodes Flate DecodeParms inline image payload before accepting EI boundaries
PASS accepts filtered inline image EI after decoded sample floor is reached
PASS decodes LZW DecodeParms inline image payload before Indexed RGB preview
FAIL requires RunLength EOD before inline image decode preview accepts supplied samples (lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php)
Expected exception InvalidArgumentException was not thrown
PASS resolves current indirect inline image decode operands before Indexed RGB preview
PASS resolves current indirect inline ImageMask geometry before stencil preview

1 test files, 105 assertions, 1 failures
```

## Evidence

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS requires ASCII85 inline image end marker before accepting delimiter-looking EI bytes
PASS requires ASCII85 inline image review payload terminator before RGB preview decoding
PASS decodes Flate DecodeParms inline image payload before accepting EI boundaries
PASS accepts filtered inline image EI after decoded sample floor is reached
PASS decodes LZW DecodeParms inline image payload before Indexed RGB preview
PASS requires RunLength EOD before inline image decode preview accepts supplied samples
PASS resolves current indirect inline image decode operands before Indexed RGB preview
PASS resolves current indirect inline ImageMask geometry before stencil preview

1 test files, 105 assertions, 0 failures
```

Adjacent inline/image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 938 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke emitted `runlength_inline_eod_present=true`, `runlength_inline_preview_decoded=true`, `runlength_inline_palette_indexes=[0,1,3]`, `runlength_missing_eod_supplied_sample_bypass_rejected=true`, `visible_text_imported=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with eight clean Gutenberg paragraph blocks.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered inline image sample-length `EI` validation, ASCII85 explicit terminator review, Flate DecodeParms delimiter validation, filtered sample-floor acceptance, indirect inline preview operand resolution, LZW DecodeParms preview rows, inline DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, inline ImageMask preview rows, inline Indexed JBIG2/palette/alpha previews, inline filter-array null alignment, object-stream inline-image repair, image XObject payload exclusion, or standalone RunLength stream-filter extraction.

The bounded behavior is specifically RunLengthDecode EOD handling for inline image decode previews and fail-closed supplied-sample bypass prevention for failed native inline filters.

## Dependency Closure

No new support component is needed. This reuses the native PHP inline image dictionary expander, content tokenizer, RunLength stream decoder, packed-sample reader, Decode mapper, `PdfTextExtractor`, `PdfImageRenderer`, and the existing WordPress smoke path. Full OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
