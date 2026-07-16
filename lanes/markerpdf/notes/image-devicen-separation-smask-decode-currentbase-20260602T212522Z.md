# markerPDF DeviceN/Separation SMask Decode Current Base

Session: `port-dev-markerpdf-image59-20260602T212234Z`
Micro-slice: `image-devicen-separation-smask-decode-currentbase`
Base accepted HEAD: `7a7220f52fd6cdbbaea942c909b4d8b982da4bfa`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders page and crop images through PDFium and converts them to RGB before inserting image Markdown spans. The native PHP port keeps that image-preview boundary without executing Python, pypdfium, PIL, models, or external PDF tools.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

PDF source behavior covered by this slice: `/Separation` and `/DeviceN` image streams can use alternate color spaces and tint transforms, while an image `/SMask` supplies alpha samples. The soft-mask image `/Decode` maps raw mask samples into opacity before RGB preview composition; the current object reference for `/SMask` must be authoritative over unrelated stale mask streams.

## Native Behavior Added

`PdfImageRenderer::alternateColorantStreamPreviewRows()` now exposes a `soft_mask_decode_review` row for direct Separation/DeviceN stream previews. The row records:

- whether a soft mask is present;
- the current `/SMask` source object;
- whether the soft mask came from the current object map;
- whether the current filter chain decoded natively;
- the soft-mask Decode source, zero/max opacity, inversion state, mismatch state, and applied-before-RGB flag.

The new focused test covers:

- a `/Separation /Spot#20Red` image decoded through ASCIIHex + Flate with image `/Decode [1 0]`;
- an inverted current soft mask from object `42 0 R`, decoded through ASCIIHex + Flate and `/Decode [1 0]`;
- a `/DeviceN [/Spot#20Blue /Spot#20Varnish]` image decoded through Flate with image `/Decode [0 1 1 0]`;
- a current soft mask from object `77 0 R` with non-identity `/Decode [0.25 0.75]`;
- stale soft-mask bytes not leaking into review metadata or visible WordPress output.

`examples/wordpress-pdf-image-devicen-separation-smask-decode-currentbase.php` models the WordPress image review path and emits current SMask source objects, alpha values, output RGB mode, and execution flags while keeping image/SMask payloads review-only.

## Evidence

Focused direct test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageDeviceNSeparationSmaskDecodeCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes current Separation image and inverted soft-mask streams before RGB preview
PASS decodes current DeviceN stream rows and non-identity soft-mask Decode alpha

1 test files, 56 assertions, 0 failures
```

Related image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageDeviceNSeparationSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageIndexedDeviceNSoftMaskTransferCurrentBaseTest.php lanes/markerpdf/tests/PdfImageIccSoftMaskDecodeTransparencyCurrentBaseTest.php lanes/markerpdf/tests/PdfImageCalibratedJbig2SoftMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageJpxSmaskColorSpacePdfaCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
12 PASS lines
6 test files, 286 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-devicen-separation-smask-decode-currentbase.php
```

The smoke emitted `source_object=42`, `source_object=77`, `decode_source=explicit`, `separation_second_alpha=0.498039`, `devicen_first_alpha=0.75`, `devicen_second_alpha=0.25`, `smask_payload_included=false`, `executes_python_or_models=false`, `executes_pypdfium_or_pil=false`, and `executes_external_pdf_tools=false`.

Required checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageDeviceNSeparationSmaskDecodeCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-devicen-separation-smask-decode-currentbase.php
python3 -m json.tool lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All passed locally. Root harness status: not run - isolated micro-slice.

Status delta: behavior tests `843 -> 844` pass / `0` fail.

## Non-Overlap

This does not repeat accepted ICCBased SMask stream decode, Indexed DeviceN palette rows, DeviceGray transfer-function SMask rows, calibrated/JBIG2 review-only SMask decode, JPX SMaskInData/PDF-A review, DCT CMYK Decode review, ColorKey masks, inline image masks, generic image filter text exclusion, or earlier DeviceN sample-only preview metadata.

The bounded behavior here is direct `/Separation` and `/DeviceN` image stream rows plus current-object `/SMask` Decode review metadata before WordPress RGB image preview.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF dictionary/value parser, image filter decoder, Decode mapping, alternate color-space planner, soft-mask alpha preview, and WordPress smoke path. Full live raster parity remains dependency-gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, model inference, pypdfium, PIL, Poppler, Ghostscript, Tesseract, or any external PDF tool.
