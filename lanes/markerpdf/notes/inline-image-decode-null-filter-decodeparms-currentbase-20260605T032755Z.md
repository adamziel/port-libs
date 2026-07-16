# markerPDF Inline Image Null-Filter DecodeParms Boundary

Session: `port-dev-markerpdf-inline-image-decode-20260605T032755Z`
Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T032755Z`
Base accepted HEAD: `b6d80ef86c77afda76f2318400f9167f2fb82004`

## Source Truth

Upstream `sddai/markerPDF` at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders PDF page and crop images through PDFium/PIL and converts rendered image output to RGB before image insertion. In this no-GPU native PHP lane, inline image parsing therefore stops at a parser/pre-raster boundary: `BI ... ID ... EI` bytes remain image payload, not WordPress paragraph text, while parser metadata needed for future RGB preview must be accurate.

PDF filter arrays can contain `null` entries, and `/DecodeParms` arrays align by filter-array slot. For an inline image with `/F [null /Fl] /DP [null << /Predictor 12 ... >>]`, the Flate predictor dictionary belongs to the second filter slot. Compact public filter metadata may omit the null slot, but native decoding must not compact the slots before applying DecodeParms.

## Behavior

`PdfImageRenderer::decodeImageStreamByFilters()` now keeps the original filter array, skips `null` slots while decoding, and uses the existing slot-aware `decodeParmsValueForImageFilterIndex()` helper for each concrete filter. Public preview metadata remains compact (`["FlateDecode"]`), while the decoder applies the slot-aligned PNG predictor dictionary before sample rows are mapped into the RGB preview boundary.

This prevents the predictor row-type byte from becoming an image sample for inline DeviceGray/RGB preview rows and keeps inline payload text excluded from WordPress content.

## Red First

With the new focused test added before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL aligns null filter DecodeParms slots before inline image RGB preview (lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 3
Actual: 4

1 test files, 137 assertions, 1 failures
```

The decoded stream was `00414243` instead of `414243`, proving the slot-aligned `/DecodeParms` predictor was skipped after `/Filter` was compacted.

## Evidence

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS requires ASCII85 inline image end marker before accepting delimiter-looking EI bytes
PASS requires ASCII85 inline image review payload terminator before RGB preview decoding
PASS decodes Flate DecodeParms inline image payload before accepting EI boundaries
PASS aligns null filter DecodeParms slots before inline image RGB preview
PASS accepts filtered inline image EI after decoded sample floor is reached
PASS decodes LZW DecodeParms inline image payload before Indexed RGB preview
PASS requires RunLength EOD before inline image decode preview accepts supplied samples
PASS fails closed on malformed inline image filter operands before WordPress text extraction
PASS fails closed on unresolved inline image filter operands before WordPress text extraction
PASS closes malformed inline image filter fallbacks before the next inline image preamble
PASS resolves current indirect inline image decode operands before Indexed RGB preview
PASS resolves current indirect inline ImageMask geometry before stencil preview

1 test files, 147 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke emits `null_filter_inline_decodeparms_aligned=true`, `null_filter_inline_public_filters=["FlateDecode"]`, `null_filter_inline_decode_failed=false`, `visible_text_imported=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent inline/image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 1169 assertions, 0 failures
```

Additional checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " OK\n"; }'
git diff --check -- lanes/markerpdf
```

All passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline image payload exclusion, ASCII85 explicit terminator checks, Flate predictor delimiter validation, filtered sample-floor acceptance, LZW DecodeParms preview rows, RunLength EOD fail-closed handling, indirect inline preview operands, inline ImageMask geometry, inline DCT/JPX/JBIG2/CCITT preview-only framing, CCITT null-filter metadata alignment, image XObject payload exclusion, or standalone stream-filter extraction.

The bounded behavior is specifically native inline image preview decoding when the filter array contains `null` entries before a concrete native filter and `/DecodeParms` is supplied as a slot-aligned array.

## Dependency Closure

No new support component is needed. This reuses the native PHP inline image dictionary expander, image filter parser, DecodeParms array parser, Flate predictor decoder, packed sample reader, RGB preview planner, `PdfTextExtractor`, `PdfImageRenderer`, and the existing WordPress smoke path. Full OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
