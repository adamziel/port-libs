# markerPDF DeviceN ICC SMask Transfer Current Base

Session: `port-dev-markerpdf-image76-20260602T224546Z`
Micro-slice: `image-devicen-icc-smask-transfer-currentbase`
Base accepted HEAD: `46dcbc383630b2d55e601d02ab9f1a9bd647b8e2`

## Source Truth

Upstream `sddai/markerPDF` is pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.

- `marker/pdf/images.py::render_image()` renders a PDFium page at `dpi / 72`, disables annotation drawing, converts the PIL image to RGB, and `render_bbox_image()` crops that RGB image for image spans: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- `marker/images/extract.py::extract_page_images()` inserts those rendered crops as image spans without exposing image payload bytes as visible text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

The native PHP lane still does not execute PDFium, PIL, Python models, or a raster backend. This slice keeps the same RGB-preview boundary while making the parser-side prerequisites reviewable: DeviceN colorant samples, an ICCBased alternate color space, a transparency-group soft mask, and a supported FunctionType 2 `/TR` transfer function.

## Behavior

`PdfImageRenderer::alternateColorantStreamPreviewRows()` now accepts optional supplied transparency-group soft-mask samples. This is intentionally bounded: decoded DeviceN image samples are native, but soft-mask group alpha is only applied when a caller supplies already-rasterized alpha/luminosity samples. Without supplied samples, decoded image streams still fail closed instead of pretending to rasterize the transparency group.

The new focused fixture proves:

- a current decoded DeviceN image stream with `/ColorSpace [/DeviceN ... ICCBased ...]` preserves the ICC profile metadata and tint-transform review boundary;
- `/SMask << /S /Luminosity /G ... /TR 95 0 R >>` with an ICCBased one-component transparency group applies supplied samples through the Type 2 transfer function before RGB preview alpha;
- incomplete or multi-component supplied alpha rows are rejected before preview rows are produced.

## WordPress Smoke

`examples/wordpress-pdf-image-devicen-icc-smask-transfer-currentbase.php` emits a Gutenberg image block with `data-marker-image-review="devicen-icc-smask-transfer"`, `data-marker-alternate-color-space="ICCBased"`, `data-marker-smask-group-color-space="ICCBased"`, `data-marker-transfer-object="95"`, and alpha rows `0.15` / `0.55`. The smoke fails fast if the DeviceN ICC metadata, supplied SMask samples, or transfer-function alpha rows are missing.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageDeviceNIccSmaskTransferCurrentBaseTest.php`
  - `1 test files, 50 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-image-devicen-icc-smask-transfer-currentbase.php`
  - emitted the expected WordPress image review metadata with no Python, PDFium/PIL, model, or external PDF-tool execution flags.

Final lint, adjacent image gate, JSON validation, and `git diff --check -- lanes/markerpdf` are recorded in the handoff response.

## Status Delta

- Behavior tests: `930 -> 932` pass / `0` fail
- Focused assertions: `+50`
- WordPress smoke: added `wordpress-pdf-image-devicen-icc-smask-transfer-currentbase.php`
- Mapped denominator: unchanged in this isolated slice; no manifest denominator row was added.

## Non-Overlap

This does not repeat accepted Separation/DeviceN CCITT preview filters, DeviceN JPX transfer-mask review-only metadata, DeviceN ICCBased decoded image streams with image-XObject soft masks, Indexed soft-mask transfer functions, DeviceGray soft-mask transfer samples, named color-space soft masks, inline filter palette alpha rows, inline JPX ColorKey previews, JPX PDF/A color context, ColorKey masks, image `/Decode` stencils, or generic ICC soft-mask decode validation.

The new behavior is specifically decoded DeviceN ICCBased image rows plus supplied transparency-group soft-mask samples passed through `/TR` before RGB preview metadata.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF dictionary/value parser, stream-filter decoder, packed sample reader, DeviceN/Separation alternate-colorant planner, ICC profile metadata planner, soft-mask transfer-function parser, and WordPress smoke renderer. Full live raster parity for transparency groups remains gated on PDFium/PIL or a future native raster backend; this patch does not execute Python, models, pdftext, pypdfium, PIL, Poppler, Ghostscript, OCR, or external PDF tools.
