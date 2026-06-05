# markerPDF CCITT Fax coding mode boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T052000Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T052000Z`
Base accepted HEAD: `689a1d63f07b4ac9ee6dd4da0f28692001c18354`

## Source truth

Upstream `sddai/markerPDF` at the manifest-pinned commit routes searchable PDF text separately from image rendering. CCITT Fax image bytes remain raster/image-review payloads, not WordPress paragraph text. The native no-GPU PHP lane maps the PDF parser boundary: `/CCITTFaxDecode` and `/CCF` review rows now expose the effective `/K` coding mode and expected end-of-block marker family while still declaring `native_raster_decode=false`.

The mapped PDF boundary is:

- missing `/DecodeParms` uses PDF CCITT defaults, including `K=0` and `EndOfBlock=true`;
- `K=0` is recorded as `group3_one_dimensional` with an RTC end marker when end-of-block is enabled;
- `K>0` is recorded as `group3_mixed_two_dimensional` with the line interval preserved;
- `K<0` is recorded as `group4_two_dimensional` with EOFB end marker metadata;
- `/EndOfBlock false` records no terminal marker requirement.

## Patch

- `PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now emits `ccitt_fax_coding_boundary` for inline/image preview planning.
- `PdfTextExtractor::extractImageXObjectBoundaryReview()` now emits the same coding boundary for Image XObjects.
- Nested soft-mask, explicit-mask, and alternate-image CCITT reviews get the same coding boundary through `nestedImageXObjectCcittFilterReview()`.
- The existing WordPress CCITT smoke now verifies and emits inline default missing-DecodeParms coding metadata and direct EOFB/RTC marker names.

## Red-first evidence

Before production changes, the new focused test failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
FAIL records CCITT Fax coding mode and terminal marker boundaries without raster decode
Actual: NULL
1 test files, 198 assertions, 1 failures
```

## Verification

Focused CCITT run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 208 assertions, 0 failures
```

Adjacent image/filter/text run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
4 test files, 1523 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
inline_ccitt_coding_mode=group3_one_dimensional
inline_default_decode_parms_present=false
inline_default_end_of_block_marker=rtc
direct_ccitt_eofb_marker=eofb
direct_ccitt_rtc_marker=rtc
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax and JSON checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR);'
```

## Non-overlap

This does not repeat accepted CCITT image-only stream exclusion, raw DecodeParms extraction, invalid DecodeParms fail-closed metadata, effective geometry metadata, escaped DecodeParms key lookup, inline CCITT review-only notes, null-filter DecodeParms alignment, Flate/Crypt prefix recovery, direct EOFB/RTC stream ownership repair, nested SMask/Mask/Alternate image review, DCT/JPX/JBIG2 preview-only filters, or generic inline-image payload exclusion. The new behavior is only the explicit coding-mode and terminal-marker review metadata derived from effective `/K` and `/EndOfBlock`.

## Dependency closure

No new support component is needed. This reuses native PDF dictionary parsing, DecodeParms normalization, image filter metadata planning, Image XObject review, and the WordPress smoke path. Full CCITT raster decoding remains out of scope for this no-GPU slice and would require a future native raster backend or PDFium/PIL-equivalent support with separate fixtures before activation.
