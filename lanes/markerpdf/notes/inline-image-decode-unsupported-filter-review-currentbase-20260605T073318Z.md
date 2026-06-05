# markerPDF Inline Image Unsupported Filter Review Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T073318Z`

Base accepted HEAD: `88a8419ba92b8f337496965543b0c0d6dd9ebd3d`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed PDF extraction before image/OCR/model stages. At this boundary, inline image `BI ... ID ... EI` bytes are raster or encrypted image payload, not WordPress paragraph text.

The native no-GPU PHP port already keeps unsupported inline filters such as `/Crypt` closed during text tokenization. This slice fixes the adjacent review metadata boundary: the cheap inline-image review plan must not advertise an unsupported filter as native raster-decodable before RGB preview planning.

## Implementation

`PdfImageRenderer::inlineImageReviewPlan()` now classifies unsupported inline image filters separately from native decoders, preview-only raster filters, and malformed/unresolved filter operands. When an inline image declares `/F /Crypt`, the review plan now records:

- `image_filter_boundary.unsupported_filters=["Crypt"]`
- `image_filter_boundary.native_raster_decode=false`
- `inline_image.unsupported_filters=["Crypt"]`
- `inline_image.native_raster_decode=false`
- `inline_image_review_only=true`

The sample preview path already rejected `/Crypt`; this patch makes the earlier review metadata fail closed too.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL marks unsupported inline image filters as review-only before RGB preview metadata (lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Crypt',
)
Actual: NULL
1 test files, 224 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 230 assertions, 0 failures
```

Adjacent inline-image/image-renderer family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 13 selected test files (root lock skipped)
13 test files, 1795 assertions, 0 failures
```

DCT/CCITT adjacent filter-boundary guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 521 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke exits 0 and emits `unsupported_inline_filter_review_only=true`, `unsupported_inline_filter_preview_rejected=true`, `visible_text_imported=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax, status, and whitespace:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed; `git diff --check -- lanes/markerpdf` produced no output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, unfiltered sample-length `EI` validation, ASCII85/ASCIIHex/Flate/LZW/RunLength decode boundaries, filtered sample-floor acceptance, surplus sample metadata, terminal whitespace samples, indirect inline preview operands, inline ImageMask geometry, malformed/unresolved filter operands, DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, unsupported-filter text-tokenizer fallback, object-stream inline-image repair, or image XObject payload exclusion.

The bounded new behavior is specifically inline image review metadata for unsupported filter names such as `/Crypt` before RGB preview planning.

## Dependency Closure

No new support component is needed. This reuses the native inline image dictionary parser, image filter metadata planner, preview-boundary decoder dispatch, `PdfTextExtractor`, `PdfImageRenderer`, and the existing WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
