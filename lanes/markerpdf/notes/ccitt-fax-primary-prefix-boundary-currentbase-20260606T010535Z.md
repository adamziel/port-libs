# CCITT Fax Primary Prefix Boundary Current Base

Session: `port-dev-markerpdf-ccitt-fax-filter-20260606T010535Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T010535Z`
Accepted base: `77c1544413102a40f5eff045cbae96edd32c5b21`

## Source Truth

Upstream markerPDF keeps searchable PDF text extraction separate from image raster rendering and OCR/model paths. This no-GPU PHP lane preserves that split: CCITT Fax remains a preview-only image filter, while native parser metadata can still describe filter boundaries and safe prefix bytes before the unsupported fax stage.

PDF stream filters are applied in order. For a primary Image XObject with `/Filter [/FlateDecode /CCITTFaxDecode]`, the native Flate prefix can be decoded safely up to, but not through, the first CCITT Fax filter. The review row must report that handoff without claiming full raster decode or leaking image payload into visible text.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now records native-prefix metadata for primary Image XObject rows before the first CCITT Fax filter:

- `native_prefix_decoded=true`
- `native_prefix_decoded_length`
- `native_prefix_decoded_sha256`
- `native_prefix_decoded_preview_hex`
- `stopped_before_filter=CCITTFaxDecode`

The row still reports `decoded_with_current_filters=false`, `native_raster_decode=false`, and `preview_only_filters=["CCITTFaxDecode"]`.

Red-first probe before the source edit: a primary `/FlateDecode /CCITTFaxDecode` Image XObject review entry exposed `ccitt_fax_filter_boundary.native_prefix_filters=["FlateDecode"]` but did not include `native_prefix_decoded` or `stopped_before_filter`.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records native prefix decoded bytes before primary CCITT Fax XObject review handoff
1 test files, 759 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-primary-prefix-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-primary-prefix-boundary-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-primary-prefix-boundary-currentbase.php
emits primary_prefix_native_decoded=true, primary_prefix_native_decoded_length=3,
primary_prefix_stopped_before_filter=CCITTFaxDecode,
ccitt_image_stream_review_only=true,
compressed_payload_excluded_from_review=true,
executes_python_or_models=false, executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted CCITT raw image-byte exclusion, direct DecodeParms parsing, malformed or unresolved DecodeParms handling, null-filter alignment, escaped key handling, soft-mask native-prefix metadata, coding-mode metadata, ImageMask polarity, EOFB/RTC/row-height boundaries, post-CCITT filter boundaries, inline image tokenization, DCT/JPX/JBIG2 preview boundaries, or Flate/Crypt/ASCII85/LZW/RunLength stream owner recovery. The new bounded behavior is only primary Image XObject review metadata for native prefix bytes before the first CCITT Fax filter.

## Dependency Closure

No new support component is required. The patch reuses the native PHP PDF object parser, existing stream filter decoder, CCITT review metadata, and lane-local WordPress smoke. Full CCITT Fax raster decoding remains out of scope under the current no-GPU/no-external-tools markerPDF lane unless a future native raster backend is explicitly activated.
