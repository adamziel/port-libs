# markerPDF CCITT Fax Nested Image Boundary Current Base

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T041422Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T041422Z`
Base accepted HEAD: `6d85ffdbe77273ccda143c6bc7574c543488d633`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes image rendering through `marker/pdf/images.py::render_image()` / `render_bbox_image()` and delegates low-level PDF stream parsing to PDFium/pdftext before RGB conversion. Under the current no-GPU lane rule, this native PHP slice preserves the parser-side image-filter handoff metadata without executing PDFium, pypdfium, PIL, OCR, models, Python workers, or external PDF tools.

Existing current-base CCITT coverage already handled primary Image XObject `/CCITTFaxDecode` and `/CCF` review metadata, inline-image CCITT review, malformed `/DecodeParms` fail-closed review, Flate-prefix and direct EOFB/RTC stream boundaries, escaped DecodeParms keys, null filter alignment, and effective geometry. The missing boundary was nested image streams referenced from a primary Image XObject via `/SMask`, explicit `/Mask`, and `/Alternates`: those streams were payload-safe but did not carry the same CCITT DecodeParms/effective-geometry review metadata.

## Red-First Gap

A pre-change local probe built one primary image with:

- `/SMask 7 0 R` using `/Filter /CCF /DecodeParms << /K -1 /Columns 16 /Rows 2 ... >>`
- `/Mask 8 0 R` using `/Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 8 /Rows 1 ... >>`
- `/Alternates [<< /Image 9 0 R /DefaultForPrinting true >>]` using `/Filter /CCF /DecodeParms << /EncodedByteAlign true ... >>`

Visible text stayed clean, but these nested paths returned `null` for `filter_details` and `ccitt_fax_decode_boundary`.

## Implementation

`PdfTextExtractor` now reuses the existing primary-image CCITT filter detail and boundary helpers for nested image streams. When a nested `/SMask`, explicit `/Mask`, or `/Alternates` stream contains `/CCITTFaxDecode` or `/CCF`, its review row includes:

- `filter_details`
- `ccitt_fax_decode_boundary`

Non-CCITT nested image reviews do not receive these extra fields, preserving the accepted review shape for DCT/JPX/JBIG2/Flate and ordinary mask/alternate cases.

## Verification

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 175 assertions, 0 failures
```

Adjacent image/filter/text sweep:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result:

```text
5 test files, 1436 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-nested-image-boundary-currentbase.php
```

Smoke metadata reports:

- `soft_mask_preview_filters=["CCF"]`
- `soft_mask_ccitt_k=-1`
- `explicit_mask_preview_filters=["CCITTFaxDecode"]`
- `explicit_mask_effective_width=8`
- `alternate_preview_filters=["CCF"]`
- `alternate_encoded_byte_align=true`
- `nested_payload_in_visible_text=false`
- `native_raster_decode=false`
- all Python/model/PDFium/PIL/external-tool flags false

Syntax checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-nested-image-boundary-currentbase.php
```

All reported no syntax errors.

## Non-Overlap

This does not repeat accepted primary Image XObject CCITT filter metadata, malformed DecodeParms fail-closed review, inline CCITT review, stream-filter DecodeParms fail-closed text decoding, DCT/JPX/JBIG2 preview-only image boundaries, direct CCITT EOFB/RTC stream owner boundaries, Flate-prefix CCITT stale-length repair, or generic Image XObject payload exclusion.

The bounded new behavior is specifically nested CCITT Fax filter review metadata for soft-mask, explicit-mask, and alternate image streams before WordPress image review.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream parser, image XObject review path, filter-name resolver, DecodeParms parser, and WordPress smoke renderer. Full CCITT raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; no Python, OCR, model, PDFium, PIL, or external PDF tool execution was run.
