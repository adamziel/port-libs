# markerPDF JPX SMask ColorSpace PDF/A Current Base

Session: `port-dev-markerpdf-image55-20260602T2109Z`
Micro-slice: `image-jpx-smask-colorspace-pdfa-currentbase`
Base accepted HEAD: `c246260033e061f468722755bd7ed5aed0b39863`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders page/image previews through PDFium and converts PIL images to RGB in `marker/pdf/images.py`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

The PHP port still does not execute Python, pypdfium, PIL, model code, or a native JPX raster backend. This slice records the PDF parser boundary that must be preserved before any future RGB preview conversion.

## Behavior

`PdfImageRenderer::jpxSoftMaskColorSpacePdfaReviewPlan()` now combines existing JPX image filter review, image color-space parsing, embedded `SMaskInData` or external `/SMask` review, and current PDF/A OutputIntent metadata.

The current PDF/A OutputIntent supplies document color-profile context for DeviceGray, DeviceRGB, and DeviceCMYK-like image streams before RGB preview planning. Image-local ICCBased and calibrated color spaces remain authoritative for the image, while the document OutputIntent remains attached as PDF/A review context.

Raw JPX bytes and decoded soft-mask payload bytes stay out of public JSON. The plan remains review-only with `native_jpx_raster_decode=false`.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageJpxSmaskColorSpacePdfaCurrentBaseTest.php` -> `1 test files, 40 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-image-jpx-smask-colorspace-pdfa-currentbase.php` -> emitted a WordPress image block with `data-marker-pdfa-profile-source="pdfa_output_intent"` and no stale OutputIntent leakage

Final lint, adjacent focused gate, JSON validation, and `git diff --check -- lanes/markerpdf` were run after the lane artifacts were updated.

## Status Delta

- Behavior tests: `818 -> 821` pass / `0` fail
- Mapped semantics: `575 -> 576 / 78`
- WordPress smoke: added `wordpress-pdf-image-jpx-smask-colorspace-pdfa-currentbase.php`

## Non-Overlap

This does not repeat accepted JPX/JBIG2 filter exclusion, JPX SMaskInData ColorKey suppression, inline JPX SMask decode boundaries, DeviceN transfer JPX review, named color-space SMask review, ICC soft-mask Decode review, calibrated/JBIG2 soft-mask review, generic PDF/A metadata extraction, or associated-file PDF/A name-tree review. The bounded behavior is PDF/A OutputIntent color-management context on JPX/SMask image review at the current xref base.

## Dependency Closure

No new support component is needed. The slice reuses `PdfMetadataExtractor` OutputIntent/PDF-A metadata, the native PDF dictionary/value parser, current xref metadata extraction, image filter metadata, SMask/ICC/ColorSpace helpers, and WordPress example rendering. Full live raster parity remains gated on a PDFium/PIL-compatible preview path or a native JPX raster backend.
