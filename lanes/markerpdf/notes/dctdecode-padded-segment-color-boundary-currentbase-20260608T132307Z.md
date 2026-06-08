# markerPDF DCTDecode padded segment color boundary current-base

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T132307Z`

Base accepted HEAD: `f2c68bcb90cae7f8d5c420ad4c2ba78bf326142c`

## Source Truth

- Upstream `sddai/markerPDF` at the manifest commit routes searchable PDF text through PDF text extraction while image bytes are handled by the render-image path before RGB conversion.
- In this native no-GPU PHP lane, `/DCTDecode` image bytes stay review-only, but parser-side JPEG APP14 and SOF metadata still drives deterministic CMYK/YCCK RGB preview planning for future raster handoff.
- PDF stream boundary recovery already accepts UTF-8 BOM bytes and JPEG marker-fill bytes before SOI. Segment metadata parsing must use the same SOI boundary instead of assuming the JPEG starts at byte zero.

## Behavior

`PdfImageRenderer::jpegMarkerSegments()` now starts segment iteration from `dctPreviewSoiOffset()`, then skips marker-fill bytes through SOI before reading APP14/SOF segments. This aligns color metadata parsing with the existing DCT stream-boundary scanner.

The focused fixture uses:

```text
UTF-8 BOM + FF FF D8 + APP14 Adobe transform 2 + SOF0 with 4 components + EOI
/Filter /DCTDecode
/ColorSpace /DeviceCMYK
/DecodeParms << /ColorTransform 0 >>
```

Before the patch, the stream boundary was review-only and valid, but `dctDecodeImageColorPlan()` returned `adobe_app14_transform = null`, so the Adobe transform did not override `/DecodeParms /ColorTransform 0`. After the patch, it reports four JPEG components, APP14 transform `2`, effective transform `2`, `adobe_marker_overrides_decode_parms=true`, and `uses_ycck_transform=true`.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodePaddedSegmentColorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL reads DCTDecode APP14 and SOF metadata after BOM and JPEG marker-fill bytes
Values are not identical
Expected: 2
Actual: NULL
1 test files, 4 assertions, 1 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodePaddedSegmentColorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reads DCTDecode APP14 and SOF metadata after BOM and JPEG marker-fill bytes
1 test files, 24 assertions, 0 failures
```

Adjacent DCT/renderer family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfDctDecode.*Test.php') lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 27 selected test files (root lock skipped)
27 test files, 2205 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-padded-segment-color-boundary-currentbase.php
```

The smoke emits `jpeg_soi_offset=3`, `jpeg_marker_fill_byte_count=1`, `jpeg_components=4`, `adobe_app14_transform=2`, `decodeparms_color_transform=0`, `effective_color_transform=2`, `adobe_marker_overrides_decodeparms=true`, `uses_ycck_transform=true`, `dctdecode_image_payload_excluded_from_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax/status/whitespace checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfDctDecodePaddedSegmentColorBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-padded-segment-color-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCTDecode stream exclusion, APP-segment false-EOI recovery, SOS entropy scan recovery, PDF comment terminators, post-EOI surplus clipping, BOM/marker-fill stream boundary recovery alone, inline DCT padded SOI tokenization, DCT filter aliases, escaped filter names, duplicate/malformed filter operands, DecodeParms ownership or duplicate handling, CCITT/JPX/JBIG2 preview-only filters, or raster execution.

The bounded behavior is specifically renderer-side JPEG APP14/SOF segment metadata parsing for DCTDecode color planning when the JPEG stream starts with UTF-8 BOM bytes and JPEG marker-fill before SOI.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, DCT/JPEG SOI/EOI boundary helper, Image XObject review path, DCT color planner, focused PHP tests, and WordPress smoke renderer. Full live JPEG raster parity remains gated on PDFium/pypdfium2/PIL or a future native raster backend; live OCR/model execution remains intentionally out of scope under the no-GPU markerPDF directive.
