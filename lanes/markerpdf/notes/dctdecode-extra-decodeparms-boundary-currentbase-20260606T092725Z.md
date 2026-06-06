# markerPDF DCTDecode Extra DecodeParms Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260606T092725Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T092725Z`
Base accepted HEAD: `4327484a8280109407f012fb0dae9c93df0ee813`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from image raster rendering. DCTDecode JPEG image bytes are therefore not searchable paragraph text, while DCT `/DecodeParms /ColorTransform` remains parser-side metadata for future RGB preview decisions.

PDF filter arrays align `/DecodeParms` entries by filter slot unless the native parser can prove a compact non-null-filter mapping. A non-null DecodeParms dictionary beyond the filter array has no filter owner. For DCTDecode, that means a candidate `/ColorTransform` must fail closed instead of being silently applied to CMYK/YCCK preview planning.

## Red First

Before this source edit, the accepted base accepted the aligned DCT slot and ignored the extra slot:

```text
php -r 'require "tools/bootstrap.php"; $r = new PortLibs\MarkerPDF\PdfImageRenderer(); ...'
[{"type":"DCTDecode","color_transform":1,"valid_color_transform":true},true,1,["render_rgb_preview_from_cmyk","apply_ycck_to_cmyk_before_rgb"]]
```

That showed `image_filter_details[0].decode_parms.valid_color_transform=true`, `dctDecodeImageColorPlan().decode_parms_color_transform_valid=true`, and `effective_color_transform=1` for `/Filter [/DCTDecode] /DecodeParms [<< /ColorTransform 1 >> << /ColorTransform 2 >>]`.

## Implementation

- `PdfImageRenderer::imageFilterDetails()` now reports DCTDecode extra non-null DecodeParms slots as `unaligned_dctdecode_decodeparms_fail_closed` with `decode_parms_alignment=unapplied_filter_slot`.
- `PdfImageRenderer::dctDecodeImageColorPlan()` now treats those unapplied DecodeParms slots as invalid alignment, ignores the ambiguous `/ColorTransform`, and falls back to the safe CMYK transform default.
- `PdfTextExtractor::imageXObjectFilterDetails()` now emits the same DCTDecode fail-closed review metadata in page Image XObject boundary rows.
- `examples/wordpress-pdf-dctdecode-extra-decodeparms-currentbase.php` demonstrates the WordPress import path without invoking models, PDFium/PIL, or external PDF tools.

## Verification

Focused DCT boundary file:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 661 assertions, 0 failures
```

Adjacent filter/detail coverage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2015 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-extra-decodeparms-currentbase.php
```

The smoke emits `decode_parms_review=unaligned_dctdecode_decodeparms_fail_closed`, `decode_parms_alignment=unapplied_filter_slot`, `unapplied_decode_parms_slots=[1]`, `decode_parms_color_transform_valid=false`, `effective_color_transform=0`, `dctdecode_image_payload_excluded_from_text=true`, and all Python/model/PDFium/PIL/external-tool execution flags false.

PHP syntax checks passed for changed PHP files:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-extra-decodeparms-currentbase.php
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCTDecode SOI/EOI stream recovery, Flate/LZW/ASCIIHex/ASCII85/RunLength prefix DCT ownership, null filter DecodeParms slot compaction before DCT, trailing null filter preservation, missing DCT DecodeParms slots, duplicate `/ColorTransform` parameters, duplicate `/DecodeParms` declarations, invalid `/ColorTransform` values, post-DCT filter reachability metadata, unsupported/Crypt Identity prefixes, malformed filter operands, APP/SOS segment parsing, post-EOI surplus clipping, inline DCT tokenization, CCITT/JPX/JBIG2 filter boundaries, or broad stream filter-stack recovery.

The bounded new behavior is only extra non-null DCTDecode DecodeParms entries that have no corresponding filter slot.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF dictionary parser, filter-stack resolver, DecodeParms slot alignment helpers, DCT color preview planner, Image XObject review metadata, and WordPress smoke path. Full JPEG raster decoding remains gated on a future native raster backend or the upstream PDFium/PIL path; OCR, Surya/Texify/Torch model execution, pypdfium, PIL, Poppler, Ghostscript, and external PDF tools were not run.
