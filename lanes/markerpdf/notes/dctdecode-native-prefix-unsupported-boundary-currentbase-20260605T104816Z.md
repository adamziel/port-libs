# DCTDecode Native-Prefix Unsupported Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T104816Z`  
Base: `3e6043929d9e6e5fc600c50f3b34e370b206774e`

## Source Truth

This is native markerPDF/PDF parser behavior only. DCTDecode image payload bytes are image data, not searchable text, and renderer previews must not claim native raster decode when a pre-DCT filter stack contains an unsupported middle filter. The bounded port behavior now decodes supported native prefixes only to validate the JPEG/DCT stream boundary, then stops fail-closed at the unsupported filter before DCTDecode.

## Red-First Evidence

Command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`

Initial result:

`1 test files, 357 assertions, 1 failures`

The new renderer case failed because `/Filter [/FlateDecode /Crypt /DCTDecode]` with stale `/Length` reported `unsupported_filters=["FlateDecode","DCTDecode"]` and used the fake compressed `endstream` marker instead of the final boundary proven by decoding the Flate prefix into a complete JPEG.

## Implementation

- `PdfImageRenderer` now records native-prefix boundary metadata for direct image preview rows even when the stop point is an unsupported non-preview filter.
- DCT stream terminator recovery now has a conservative fallback: if full pre-DCT filter decoding fails, it decodes only the native prefix before the first unsupported pre-DCT filter, and accepts the candidate terminator only when those decoded bytes form a complete JPEG.
- The renderer remains review-only and fail-closed at `/Crypt`; it does not execute OCR/models or external PDF tooling.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`  
  `1 test files, 368 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php`  
  `2 test files, 383 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php`  
  `1 test files, 516 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-native-prefix-unsupported-boundary-currentbase.php`  
  Emits `xobject_raw_length_recovered=true`, `renderer_raw_length_recovered=true`, `native_prefix_decoded_before_unsupported_filter=true`, `unsupported_middle_filter_fail_closed=true`, and model/external-tool flags false.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php`
- `php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-native-prefix-unsupported-boundary-currentbase.php`
- `git diff --check -- lanes/markerpdf`

## Non-Overlap

This slice does not repeat prior DCTDecode raw JPEG, Flate-prefix-only, null-filter DecodeParms, malformed filter operand, indirect filter, Crypt Identity, inline image, or text-extraction boundary coverage. The new behavior is the direct ICCBased renderer path where a native prefix is decodable, a later unsupported middle filter blocks native decode, and DCTDecode still requires full image payload boundary recovery.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP stream decoders, DCT boundary scanning, and renderer review metadata. GPU/model/OCR, pypdfium, PIL, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
