# CCITT Fax Preview-Prefix Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T065156Z`
Base: `6f96d2de713278a0b65fd38d292916760b47c0fc`

## Behavior

Upstream markerPDF routes image rasterization through `marker.pdf.images.render_image` using pypdfium/PIL. Under the current no-GPU/no-model scope, the native PHP lane keeps CCITTFaxDecode/CCF image payloads review-only and must not decode arbitrary preview-only raster filters before them.

This slice makes that boundary explicit for filter arrays such as `/Filter [/DCTDecode /CCF]`: `PdfImageRenderer` and `PdfTextExtractor` now include `pre_ccitt_preview_filters_block_native_prefix_decode=true` when preview-only filters appear before the first CCITT Fax filter. Native prefix filters remain empty, CCITT DecodeParms/coding metadata stays reviewable, and image payload text remains excluded from visible WordPress paragraphs.

## Evidence

- Baseline focused run before edit: `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => `1 test files / 818 assertions / 0 failures`.
- Focused run after edit: `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => `1 test files / 838 assertions / 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-preview-prefix-boundary-currentbase.php` emits `pre_ccitt_preview_filters_block_native_prefix_decode=true`, keeps `native_prefix_filters=[]`, and excludes the image payload from visible text.
- PHP lint passed for `PdfTextExtractor.php`, `PdfImageRenderer.php`, `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`, and `wordpress-pdf-ccitt-fax-preview-prefix-boundary-currentbase.php`.
- JSON validation passed for `lanes/markerpdf/lane-status.json` and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF stream-filter parser, image filter review metadata, and text extractor image-XObject boundary review. It does not execute Python, models, pypdfium, PIL, external PDF tools, or live OCR.

## Non-Overlap

This does not repeat existing terminal CCITT Fax exclusion, post-CCITT unreachable filter review, native ASCIIHex/Flate/RunLength prefix decoding before CCITT, malformed DecodeParms fail-closed, or row/EOFB/RTC stream ownership cases. It only adds the missing pre-CCITT preview-only filter blocker signal.
