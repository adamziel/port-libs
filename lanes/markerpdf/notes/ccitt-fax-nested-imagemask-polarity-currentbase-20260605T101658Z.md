# CCITT Fax nested ImageMask polarity boundary current-base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T101658Z`

Base accepted HEAD: `c34bd22c970934de29fc9d7c3cbf7a358b8b07cc`

## Source truth

Upstream markerPDF hands PDF image streams to the image/rendering path rather than treating image payload bytes as text. In the no-GPU native PHP lane, CCITTFaxDecode and CCF image streams stay review-only: text extraction must not rasterize them or leak fax payload bytes, while PDF dictionary metadata needed by WordPress media review is preserved.

Accepted current-base slices already exposed primary XObject and inline CCITT ImageMask polarity metadata, and separately exposed nested `/SMask`, explicit `/Mask`, and `/Alternates` CCITT DecodeParms boundaries. The remaining gap was the intersection: nested explicit masks and alternate images that are themselves `/ImageMask true` streams did not carry the same polarity/stencil opacity boundary as primary images.

## Red-first evidence

A temporary probe over the focused nested-mask fixture returned no nested polarity rows before the source change:

```text
mask ccitt_fax_imagemask_polarity_boundary: null
alternate ccitt_fax_imagemask_polarity_boundary: null
```

That meant WordPress review could see nested CCITT filter and DecodeParms metadata, but could not tell whether the black or white sample in an explicit mask or alternate image would be visible before any future raster backend.

## Implementation

`PdfTextExtractor::nestedImageXObjectCcittFilterReview()` now accepts the nested stream's ImageMask flag, Decode review, and effective bits-per-component. When the nested stream is CCITT and `/ImageMask true`, it reuses the existing `ccittFaxImageMaskPolarityBoundary()` helper so nested explicit `/Mask` and `/Alternates` entries expose:

- `black_is_1`, `black_sample_value`, and `white_sample_value` from CCITT DecodeParms defaults or explicit `/BlackIs1`.
- `image_mask_decode_source`, `decode_inverts_stencil`, and sample opacity from the image `/Decode` array, including default `[0 1]`.
- `review_only=true` and `native_raster_decode=false`.

Soft masks that are ordinary grayscale images still expose CCITT DecodeParms review metadata without adding ImageMask polarity.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 324 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-nested-image-boundary-currentbase.php
emits explicit_mask_imagemask_polarity, alternate_imagemask_polarity, nested_payload_in_visible_text=false, executes_python_or_models=false, executes_external_pdf_tools=false, and executes_pypdfium_or_pil=false.

php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 2126 assertions, 0 failures

php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-nested-image-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-nested-image-boundary-currentbase.php

php -r '$data=json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
no output
```

## Non-overlap

This does not repeat accepted CCITT image-only stream exclusion, raw DecodeParms extraction, malformed/unresolved DecodeParms fail-closed review, escaped DecodeParms names, null-filter DecodeParms alignment, Flate/Crypt prefix recovery, direct EOFB/RTC ownership, coding-mode metadata, CCF alias preservation, post-CCITT filter-stack reachability, primary/inline ImageMask polarity, or the earlier nested SMask/Mask/Alternate DecodeParms rows. The new behavior is only the nested ImageMask polarity handoff for explicit mask and alternate CCITT streams.

## Dependency closure

No new support component is needed. This remains native PHP parser/review metadata; full CCITT raster decoding remains outside the current no-GPU scope and would require an explicitly approved raster backend such as PDFium/PIL or a future native CCITT decoder.
