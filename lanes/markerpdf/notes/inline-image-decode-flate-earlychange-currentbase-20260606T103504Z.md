# Inline Image Flate EarlyChange DecodeParms Boundary - 2026-06-06

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T103504Z`

Accepted base: `4d7229bc3c8e868b129629e7dc6a1682afd2bc3c`

## Source Truth

This slice stays in the current no-GPU markerPDF scope. It ports a native PDF filter-boundary behavior: `/EarlyChange` is an LZW DecodeParms control and `/EarlyChange 0` on a Flate inline image must not be treated as a natively previewable raster stream. The text extractor already kept those bytes image-owned; the renderer now matches that fail-closed boundary before WordPress media preview.

## Patch

- `PdfImageRenderer` now records native Flate/LZW DecodeParms review metadata for `Predictor`, `Columns`, `Colors`, `BitsPerComponent`, and `EarlyChange`.
- Flate `/EarlyChange 0` is marked invalid/review-only before inline output preview.
- Inline output previews now expose `image_filter_details` and `image_stream.filter_details`, so valid PNG predictor rows show the exact predictor metadata used for the decoded sample boundary.
- The focused inline-image DecodeParms test adds a paired invalid Flate EarlyChange case and valid PNG Sub predictor case.
- The WordPress smoke emits `invalid_flate_earlychange_review_only`, `invalid_flate_earlychange_preview_rejected`, and `png_predictor_sub_preview_decoded` without running Python, models, or external PDF tools.

## Evidence

Red-first after adding the focused test before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 739 assertions, 1 failures`; failure was the invalid Flate EarlyChange review reporting no unsupported filter.

After source edit and smoke update:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 755 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php >/tmp/markerpdf-inline-image-smoke.html`

Result: exits `0`; generated paragraphs exclude `Flate EarlyChange Inline Noise` and include the new smoke metadata keys.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP Flate/LZW DecodeParms parser and inline image renderer; no OCR, Surya, Texify, Torch, PDFium, PIL, Python models, external PDF tools, or live services were run.

## Non-Overlap

This does not repeat the accepted inline-image work for ASCII85/ASCIIHex EOD boundaries, null filter DecodeParms slot alignment, malformed/duplicate Decode operands, JPX/JBIG2/DCT/CCITT preview-only boundaries, direct null filters, short-row predictors, post-stream surplus, wrapped terminal filters, ImageMask geometry, or indirect inline operands. The new boundary is specifically non-LZW `EarlyChange` DecodeParms on Flate inline images plus valid PNG predictor metadata exposure.
