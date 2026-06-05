# markerPDF DCTDecode Invalid ColorTransform Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260605T180731Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T180731Z`
Base accepted HEAD: `326cb32be0e29897c91ef4b3b31f5f8ebbc605c6`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and keeps image rendering separate from visible text extraction.
- Upstream image rendering in `marker/pdf/images.py::render_image()` delegates raster work to PDFium/PIL and converts the result to RGB. In the native no-GPU/no-PDFium scope, DCTDecode JPEG bytes and color-transform decisions remain deterministic review metadata before any future raster backend.
- PDF DCTDecode `/DecodeParms /ColorTransform` values outside the supported native range are not safe instructions for RGB conversion. They should be preserved for review and ignored for effective transform selection.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

## Behavior

`PdfImageRenderer::dctDecodeImageColorPlan()` now preserves the raw invalid `/ColorTransform` integer, exposes `decode_parms_color_transform_valid=false`, and marks `decode_parms_color_transform_ignored=true`. Invalid values no longer clamp to `2` and no longer trigger fabricated YCCK conversion for CMYK JPEG review; effective transform selection falls back to the existing component default.

The image filter details and page image-review rows still expose the raw invalid value with `valid_color_transform=false`, while visible WordPress text remains isolated from DCT/JPEG bytes.

## Red-First Probe

Before the source edit, a direct probe of `/DecodeParms << /ColorTransform 9 >>` reported:

```text
decode_parms_color_transform=2
effective_color_transform=2
uses_ycck_transform=true
```

After the patch, the same boundary reports:

```text
decode_parms_color_transform=9
decode_parms_color_transform_valid=false
decode_parms_color_transform_ignored=true
effective_color_transform=0
uses_ycck_transform=false
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
1 test files, 523 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRunLengthPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php
6 test files, 1136 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-dctdecode-invalid-color-transform-currentbase.php
```

The smoke emits `decode_parms_color_transform=9`, `decode_parms_color_transform_valid=false`, `decode_parms_color_transform_ignored=true`, `effective_color_transform=0`, `uses_ycck_transform=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Additional handoff checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-invalid-color-transform-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'
git diff --check -- lanes/markerpdf
```

All passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted generic DCTDecode image-filter exclusion, inline DCT tokenizer framing, direct or prefix-decoded JPEG EOI/padding ownership, DCT CMYK Adobe APP14 transform handling, valid DecodeParms ColorTransform handling, DCT `/Decode` sample review, DCT soft-mask/mask clipping, post-DCT filter review, or CCITT/JPX/JBIG2 preview-only filters.

The bounded behavior is specifically invalid DCTDecode `/DecodeParms /ColorTransform` values at the filter-review and RGB-preview planning boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF image dictionary parser, filter/DecodeParms review planner, DCT color-plan helper, text extractor, and WordPress smoke path. Full JPEG raster parity remains gated on PDFium/pypdfium2/PIL or a future native raster backend; OCR/model execution remains intentionally out of scope and was not run.
