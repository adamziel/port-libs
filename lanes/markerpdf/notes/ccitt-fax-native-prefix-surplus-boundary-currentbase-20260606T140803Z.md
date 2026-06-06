# CCITT Fax Native-Prefix Surplus Boundary Current Base

Session: `port-dev-markerpdf-ccitt-fax-filter-20260606T140803Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T140803Z`
Accepted base: `be6f8e132ff60635ee5054a4f29f12b44a650b22`

## Source Truth

This slice stays in the no-GPU markerPDF scope: native PDF parser and review metadata only. CCITT Fax remains a preview-only raster filter here, so native-prefix metadata is valid only when every native filter before the preview-only CCITT stage consumes a clean, bounded input. A complete Flate member followed by non-whitespace surplus before the CCITT stage must not be reported as a clean `native_prefix_decoded` handoff.

## Red-First Evidence

After adding the focused test on the accepted base, the CCITT boundary test failed as expected:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 883 assertions, 1 failures`, with `native_prefix_decoded` reported `true` for the surplus Flate-prefix fixture.

## Implementation

- `PdfTextExtractor` now requires bounded native-prefix filter input before the CCITT native-prefix stream boundary review can publish `native_prefix_decoded`.
- `PdfImageRenderer` now applies the explicit native-prefix boundary guard only when the decoded native prefix stops before CCITT, preserving accepted DCT/JPX review-only prefix behavior.
- The focused fixture covers both image XObject review and soft-mask review with `/Filter [/FlateDecode /CCITTFaxDecode]` and `/Filter [/FlateDecode /CCF]`.
- The WordPress smoke now proves the surplus prefix is excluded from visible text and review JSON while the review row keeps the raw filter metadata.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors
- `php -l lanes/markerpdf/src/PdfImageRenderer.php` => no syntax errors
- `php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php` => no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => `1 test files, 894 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php` => `1 test files, 702 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php` => smoke emits `surplus_prefix_native_handoff_rejected=true`, `soft_mask_surplus_prefix_native_handoff_rejected=true`, and no Python/models/external PDF tools
- `git diff --check -- lanes/markerpdf` => clean

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the earlier CCITT EOFB/RTC/row-count stream ownership work, CCITT DecodeParms alignment/fail-closed coverage, preview-only prefix reachability, or the attachment stream-filter stack short-Length recovery slice. It narrows one current-base metadata boundary: clean native-prefix handoff reporting when native prefix decoders accept trailing surplus before preview-only CCITT.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP Flate, LZW, ASCII85, and stream-boundary helpers already present in the markerPDF lane. OCR, Surya, Texify, Torch, pypdfium, PIL, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
