# markerPDF Image XObject Indirect Alternates Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260606T233823Z`  
Accepted base: `eb7d11e9bcd6594ca75065e9ce45b3589c10aa36`

## Source Truth

Upstream markerPDF treats image rendering as a media handoff boundary: image bytes are reviewed for conversion/media decisions and must not become text extraction output. PDF Image XObject `/Alternates` entries can be supplied directly as an array or indirectly through an array object. The existing PHP port already resolved many PDF arrays through `PdfTextExtractor::pdfArrayFromValue()`, including exact-generation indirect object references, but the Image XObject alternate-image path still only accepted direct array syntax.

## Behavior

`PdfTextExtractor::imageXObjectAlternateImageReviews()` now resolves the `/Alternates` value with `pdfArrayFromValue($value, $objects)`. This preserves existing direct-array behavior while adding exact-generation indirect-array resolution. The focused fixture uses:

- primary image object `5 0 R` with `/Alternates 8 0 R`;
- indirect array object `8 0 R` containing `<< /Image 6 1 R /DefaultForPrinting true >>`;
- stale same-number alternate object `6 0 R` with different decoded bytes.

The review row now selects alternate image generation `6 1`, records its FlateDecode metadata/hash, excludes the stale generation hash, and keeps primary/current/stale raster payload bytes out of visible WordPress text.

## Red-First Evidence

Before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectAlternatesBoundaryCurrentBaseTest.php`

Result:

`FAIL resolves indirect Image XObject Alternates arrays before review-only media handoff`  
`Indirect Alternates array resolves to one alternate image.`  
`Expected: 1`  
`Actual: 0`  
`1 test files, 10 assertions, 1 failures`

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`  
  `No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfImageXObjectIndirectAlternatesBoundaryCurrentBaseTest.php`  
  `No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectIndirectAlternatesBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-indirect-alternates-currentbase.php`  
  `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-indirect-alternates-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectAlternatesBoundaryCurrentBaseTest.php`  
  `1 test files, 27 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectIndirectAlternatesBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`  
  `3 test files, 1302 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-indirect-alternates-currentbase.php`  
  Emits two WordPress paragraphs and metadata with `alternate_image_count=1`, `alternate_object=6`, `alternate_generation=1`, `current_alternate_hash_selected=true`, `stale_alternate_hash_excluded=true`, `alternate_payload_excluded_from_text=true`, and no Python/model/OCR/PDFium/PIL/external PDF execution.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`  
  `lane-status json ok`
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'`  
  `manifest json ok`
- `git diff --check -- lanes/markerpdf`  
  Passed with no output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct `/Alternates` review, DCT alternate-image EOI clipping, nested CCITT alternate-image metadata, image auxiliary exact-generation `/Image` references, duplicate XObject resource-name rejection, Form-resource image review, placement/CTM review, optional-content image visibility, or primary Image XObject payload exclusion. The new boundary is specifically the `/Alternates` array object itself being indirect and resolved before the existing alternate-image review logic.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `pdfArrayFromValue()` / exact-reference object resolution path. It does not run GPU/model/OCR workloads, PDFium, PIL, external PDF tools, live services, or provider credentials.
