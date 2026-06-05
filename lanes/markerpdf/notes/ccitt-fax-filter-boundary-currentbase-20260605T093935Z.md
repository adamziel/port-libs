# markerPDF CCITT Fax Post-Filter Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T093935Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T093935Z`
Base accepted HEAD: `f43374100703845d1f334e1745142ca65dc85bf6`

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit keeps searchable PDF text on the `pdftext`/PDFium text-page path and sends image pixels through `marker/pdf/images.py::render_image()` / `render_bbox_image()` before RGB handoff. Under the current no-GPU native PHP scope, CCITT Fax raster bytes remain review-only, but filter-stack metadata still needs to be precise for WordPress media review and any future raster backend.

PDF stream filter arrays are ordered. If `/CCITTFaxDecode` or `/CCF` appears before later filters, the native PHP parser cannot honestly claim it can apply those later native filters because the preceding CCITT raster stage is preview-only.

## Native Behavior Added

`PdfImageRenderer` and `PdfTextExtractor` now add post-CCITT stack metadata to `ccitt_fax_filter_boundary`:

- `filters_after_ccitt`
- `native_filters_after_ccitt`
- `preview_only_filters_after_ccitt`
- `ccitt_is_terminal_filter`
- `post_ccitt_filters_present`
- `post_ccitt_filters_block_native_decode`

Existing `filters`, `filter_details`, DecodeParms metadata, coding-mode metadata, and payload exclusion behavior remain unchanged.

## Red-First Evidence

After adding the focused assertion and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
FAIL preserves declared CCF aliases while exposing canonical CCITT filter metadata
FAIL marks filters declared after preview-only CCITT Fax as unreachable native stages
1 test files, 289 assertions, 2 failures
```

The failures showed that `ccitt_fax_filter_boundary` stopped at the first CCITT filter and did not expose any post-CCITT filters.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 313 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageColorKeySoftMaskJpxCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageJpxSmaskColorSpacePdfaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
7 test files, 501 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
emits post_ccitt_filters_after_ccitt=["ASCIIHexDecode","FlateDecode","DCTDecode"],
post_ccitt_native_filters_blocked=["ASCIIHexDecode","FlateDecode"],
post_ccitt_filters_block_native_decode=true,
executes_python_or_models=false, and executes_external_pdf_tools=false
```

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
all reported no syntax errors
```

```text
git diff --check -- lanes/markerpdf
passed
```

## Non-Overlap

This does not repeat accepted CCITT payload exclusion, DecodeParms extraction/fail-closed handling, null-filter DecodeParms alignment, escaped filter names, identity Crypt ownership repair, EOFB/RTC marker repair, nested mask/alternate review, ImageMask polarity, coding-mode review, or CCF alias preservation. The new behavior is specifically post-CCITT filter-stack reachability metadata.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF tokenizer, filter-array reader, CCITT review metadata path, image renderer review planner, and WordPress smoke. Full CCITT raster decode remains out of scope without a future native raster backend; no Python, OCR, model, pypdfium, PIL, external PDF tool, or live-service provider execution was run.
