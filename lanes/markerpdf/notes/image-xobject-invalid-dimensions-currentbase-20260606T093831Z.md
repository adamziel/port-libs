# Image XObject Invalid Dimensions Current-Base Slice

Session: `port-dev-markerpdf-image-xobject-20260606T093831Z`
Slice: `markerpdf-image-xobject-boundary-current-base-20260606T093831Z`
Accepted base: `333ee46512d5ab2039cf170209aca42d287f1569`

## Source Truth

- Upstream boundary used: `marker.pdf.extract_text` excludes image stream bytes from searchable text, while `marker.pdf.images.render_image` is the RGB/native raster handoff boundary.
- PDF Image XObject dimensions are required image dictionary inputs for raster handoff. This slice keeps malformed `/Width` or `/Height` values in metadata review and does not claim a native raster decode path.
- No GPU/model/OCR/PDFium/PIL execution was run. Native raster parity remains outside the current no-GPU markerPDF scope.

## Implementation

- `PdfTextExtractor::imageXObjectBoundaryEntry()` now records `image_dimensions_valid`.
- Invalid top-level Image XObject dimensions expose `image_dimension_boundary` with raw normalized `width`/`height`, integer and positive checks, `native_raster_decode_blocked=true`, and policy `reject_missing_or_nonpositive_image_dimensions`.
- `native_raster_decode` is now true only when filters are locally decodable, no preview-only filters remain, and image dimensions are positive integers.
- Stream payloads are still decoded for review hashes where filters are supported, but never enter visible WordPress text.

## Evidence

Red-first focused check after adding the test before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
=> 1 test files, 1163 assertions, 1 failures
FAIL marks malformed image XObject dimensions as review-only before native raster handoff
Expected: false
Actual: NULL
PHP Warning: Undefined array key "image_dimensions_valid"
```

Focused passing checks after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
=> 1 test files, 1201 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectOpiProxyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
=> 3 test files, 2074 assertions, 0 failures
```

Syntax and smoke evidence:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-invalid-dimensions-currentbase.php
=> no syntax errors

php lanes/markerpdf/examples/wordpress-pdf-image-xobject-invalid-dimensions-currentbase.php
=> markerpdf-image-xobject-invalid-dimensions-currentbase: ok
=> zero_width_native_raster_decode=false
=> decimal_height_native_raster_decode=false
=> payload_in_visible_text=false
```

## Non-Overlap

This does not repeat the accepted OPI proxy, image mask, soft-mask, alternates, CCITT DecodeParms geometry fallback, JPX, artifact/marked-content, clipping, rotation/UserUnit, malformed `Do`, or encrypted image review slices. It only gates top-level Image XObject native raster handoff on positive integer dimensions.

## Dependency Closure

No new support component is needed. Existing native PDF dictionary parsing, stream filter decoding, and image review metadata helpers are reused. Full raster rendering remains intentionally deferred under the no-GPU/no-model scope.

## Next

Continue with non-overlapping image/filter metadata, font/CMap, xref repair, annotation/form, metadata, or supplied-boundary table/equation handoff behavior.
