# DCTDecode Duplicate DecodeParms Boundary - 2026-06-06

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T041527Z`

Accepted base: `d3d8e11aba7ada9dd57f82d5d806fe66392355d1`

## Source Truth

MarkerPDF remains in the no-GPU native PDF parser scope. This slice follows the existing PDF image/filter boundary already used by `PdfImageRenderer` and `PdfTextExtractor`: JPEG `/DCTDecode` streams are image payloads, not searchable text, and DCT `/DecodeParms /ColorTransform` metadata is only safe to apply when the parameter is unambiguous and valid.

The existing lane already failed closed for duplicate image `/Filter` declarations, unaligned DCT `/DecodeParms`, invalid DCT `/ColorTransform` values, and duplicate CCITT DecodeParms fields. This patch extends the same fail-closed policy to duplicate DCT `/DecodeParms /ColorTransform` declarations.

## Behavior

- Detects duplicate `/ColorTransform` keys inside the DCTDecode DecodeParms dictionary selected for the DCT filter.
- Marks `valid_color_transform=false`, records `invalid_decode_parms_fields=["color_transform"]`, `duplicate_decode_parms_fields=["color_transform"]`, and `decode_parms_review=duplicate_dctdecode_decodeparms_parameter_fail_closed`.
- Ignores the ambiguous DecodeParms transform before RGB preview planning, so CMYK DCT review falls back to the default effective transform and does not claim YCCK conversion.
- Keeps DCT/JPEG payload bytes preview-only and excluded from Gutenberg paragraphs.

## Red-First Evidence

Initial focused run before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDuplicateDecodeParmsCurrentBaseTest.php`

Result: failed because duplicate `/ColorTransform 1 /ColorTransform 0` was still reported with `valid_color_transform=true`.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDuplicateDecodeParmsCurrentBaseTest.php` => `1 test files, 26 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDuplicateDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDuplicateFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRendererStreamBoundaryCurrentBaseTest.php` => `4 test files, 735 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-duplicate-decodeparms-currentbase.php` => emitted two Gutenberg paragraphs and marker metadata with `decode_parms_review=duplicate_dctdecode_decodeparms_parameter_fail_closed`, `dct_decodeparms_color_transform_valid=false`, `dct_decodeparms_color_transform_ignored=true`, `uses_ycck_transform=false`, and all model/external-tool flags false.
- `php -l` passed for `PdfImageRenderer.php`, `PdfTextExtractor.php`, `PdfDctDecodeDuplicateDecodeParmsCurrentBaseTest.php`, and `wordpress-pdf-dctdecode-duplicate-decodeparms-currentbase.php`.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'` => `lane-status.json OK`
- `git diff --check -- lanes/markerpdf` => passed.

## Non-Overlap

This does not repeat the accepted DCT post-filter stack boundary, duplicate `/Filter` declaration boundary, DCT renderer stream boundary, CCITT duplicate DecodeParms behavior, OCR/model work, or any raster decode implementation. It is limited to duplicate DCT DecodeParms parameter review and fail-closed color-transform planning.

## Dependency Closure

No new support component is needed. The patch reuses existing native PDF dictionary parsing, Image XObject review, and DCT color planning helpers. GPU/OCR/model execution, PIL/pypdfium raster rendering, and external PDF tools remain intentionally out of scope.
