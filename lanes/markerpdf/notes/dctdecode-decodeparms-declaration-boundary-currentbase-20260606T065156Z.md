# markerPDF DCTDecode DecodeParms Declaration Boundary

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T065156Z`
- Accepted base: `6f96d2de713278a0b65fd38d292916760b47c0fc`
- Upstream source truth: `sddai/markerPDF` delegates PDF image rendering to PDFium/PIL-style raster paths while searchable text extraction remains separate. In the no-GPU PHP lane, DCTDecode JPEG bytes are review-only image payloads, and ambiguous PDF image filter metadata must fail closed before WordPress import decisions.

## Behavior

Duplicate top-level `/DecodeParms` declarations on a DCTDecode image XObject now fail closed before DCT color-transform and RGB preview planning:

- `PdfImageRenderer::imageColorSpaceSoftMaskPlan()` exposes `duplicate_dctdecode_decodeparms_declaration_fail_closed` in the DCT filter detail and notes.
- `PdfImageRenderer::dctDecodeImageColorPlan()` preserves the first declared `/ColorTransform` as review metadata but marks it invalid, ignores it for effective transform selection, and does not enable YCCK conversion.
- `PdfTextExtractor::extractImageXObjectBoundaryReview()` records the same DCT DecodeParms declaration failure on WordPress image-review rows while excluding JPEG bytes from visible paragraphs.

## Red-First Evidence

Before the source edit, this direct probe accepted the first duplicate declaration:

```bash
php -r 'require "tools/bootstrap.php"; $r=new PortLibs\MarkerPDF\PdfImageRenderer(); $dict="<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms << /ColorTransform 1 >> /DecodeParms << /ColorTransform 0 >> >>"; $plan=$r->imageColorSpaceSoftMaskPlan($dict); $color=$r->dctDecodeImageColorPlan($dict, "\xff\xd8\xff\xd9"); var_export([$plan["image_filter_details"], $plan["notes"], $color]);'
```

The probe showed `valid_color_transform=true`, `decode_parms_color_transform_valid=true`, and `uses_ycck_transform=true`.

## Verification

- `php -l lanes/markerpdf/src/PdfImageRenderer.php` passed.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfDctDecodeDecodeParmsDeclarationBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-decodeparms-declaration-boundary-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsDeclarationBoundaryCurrentBaseTest.php` passed: `1 test files, 26 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDuplicateDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDuplicateFilterBoundaryCurrentBaseTest.php` passed: `3 test files, 711 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-decodeparms-declaration-boundary-currentbase.php` passed and emitted clean WordPress paragraph output plus `duplicate_dctdecode_decodeparms_declaration_fail_closed`.

## Non-Overlap

This does not repeat accepted DCTDecode JPEG stream exclusion, inline DCT tokenization, Flate/LZW/ASCII85/ASCIIHex/RunLength prefix boundaries, null filter slots, trailing null filters, missing DecodeParms slot alignment, duplicate `/ColorTransform` parameters inside a single DecodeParms dictionary, duplicate `/Filter` declarations, malformed filter operands, invalid ColorTransform values, post-DCT filters, CMYK `/Decode` preview rows, CCITT/JPX/JBIG2 review-only filters, or any OCR/model/raster execution.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF dictionary parsing, DCTDecode filter metadata, and image XObject review code. GPU/model execution, pypdfium, PIL, Python marker runtime, and external PDF tools remain intentionally out of scope.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior: stream filters, fonts/CMaps, xref repair, metadata, annotations/forms, image/filter metadata, page geometry, and supplied-boundary handoffs.
