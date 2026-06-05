# markerPDF Image XObject JPX SMaskInData Boundary

Session: `port-dev-markerpdf-image-xobject-20260605T075331Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T075331Z`
Base accepted HEAD: `1b72408ed94109ba862fc9360cd5e316e7f53484`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable page text extraction separate from image rendering: `marker/pdf/extract_text.py` supplies text pages, while `marker/pdf/images.py` hands page/image rendering through PDFium and RGB conversion. Under the current no-GPU scope, the PHP lane records parser-side Image XObject review metadata without rasterizing JPX/JPEG2000 payloads or exposing image bytes as WordPress paragraph text.

PDF JPXDecode Image XObjects may carry embedded alpha via `/SMaskInData`. When that value is valid and nonzero, the embedded JPX alpha is authoritative for review and an external `/SMask` must not be treated as the active mask. Invalid JPX `/SMaskInData` values remain review-only metadata and leave any valid external `/SMask` authoritative.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now:

- emits `jpx_soft_mask_in_data` metadata on page Image XObject review rows;
- records valid values `1` and `2` as embedded soft-mask review, distinguishing encoded alpha values from preblended matte;
- marks external `/SMask` references as present but ignored when valid JPX embedded alpha is active;
- suppresses ColorKey mask application when embedded JPX alpha is active;
- keeps invalid JPX `/SMaskInData` values review-only while preserving the external soft-mask stream review path.

The focused fixture paints two JPX Image XObjects. The first has valid `/SMaskInData 2`, an external `/SMask`, and a ColorKey `/Mask`; review records the embedded alpha boundary, suppresses both external mask authority and ColorKey alpha, and does not serialize ignored mask payload hashes. The second has invalid indirect `/SMaskInData 9 0 R`; review records the invalid value and keeps the external soft-mask stream authoritative.

## Evidence

Red-first current-base run after adding the focused fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
FAIL records JPX SMaskInData boundaries on page image XObject review rows
Expected jpx_soft_mask_in_data review array
Actual: NULL
1 test files, 536 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 566 assertions, 0 failures
```

Image family gate:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -name 'PdfImage*Test.php' | sort)
20 test files, 1967 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-jpx-smaskindata-currentbase.php
image_xobject_count=2
invoked_image_xobject_count=2
embedded_jpx_soft_mask_present=true
embedded_smaskindata_value=1
embedded_external_smask_ignored=true
embedded_colorkey_suppressed=true
invalid_smaskindata_external_smask_used=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-jpx-smaskindata-currentbase.php
No syntax errors detected
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, page clipping, optional content, exact object generation, auxiliary stream generation, SMask stream metadata, ColorKey mask arrays, named ColorSpace resources, ExtGState review, inline JPX SMaskInData renderer planning, JPX PDF/A renderer planning, DCT/CCITT/JPX/JBIG2 preview filters, or Form-resource image discovery. The bounded behavior is specifically page Image XObject review rows honoring JPX `/SMaskInData` authority before WordPress import.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, stream dictionary parser, exact-generation object lookup, Image XObject review path, and existing JPX review semantics from the bounded renderer tests. Full JPX raster decoding and exact PDFium/Pillow pixel parity remain out of scope under the no-GPU/no-model markerPDF direction and would require a future native raster backend or explicitly authorized external renderer parity slice.
