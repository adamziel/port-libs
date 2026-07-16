# markerPDF image XObject optional-content boundary

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260604T183228Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable PDF text extraction through `marker/pdf/extract_text.py` from image rendering through `marker/pdf/images.py`. Under the current no-GPU markerPDF scope, the PHP lane owns native parser boundaries that keep raster Image XObject payloads out of WordPress paragraph text while exposing review metadata for images that PDFium would paint.

Optional content groups are a native PDF visibility boundary. Text/Form extraction already honors default-view `/OCProperties`; Image XObject review now uses the same boundary so images invoked inside hidden marked-content blocks or carried by hidden `/OC` Image XObjects stay review-only and are not counted as painted.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now:

- resolves catalog optional-content default-view states before scanning page resources;
- filters `/OC ... BDC` marked-content blocks before counting image `Do` invocations;
- treats Image XObject streams with hidden object-level `/OC` as unpainted review metadata;
- avoids traversing hidden Form XObjects for nested image-resource review;
- records `optional_content_visible` on image review entries.

The focused fixture keeps three Image XObjects in page resources: one visible image invoked in a visible layer, one image invoked only inside a hidden marked-content block, and one object-level hidden image invoked outside marked content. WordPress text contains only the page text, the review reports one invoked image and two uninvoked image resources, and no decoded image payload text is serialized.

## Evidence

Pre-patch probe with the optional-content image fixture:

```text
[3,3,0,[["Visible Image",true,1],["HiddenMarked",true,1],["HiddenObject",true,1]]]
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS counts optional-content-hidden image XObject invocations as unpainted review metadata

1 test files, 136 assertions, 0 failures
```

Adjacent text/image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
2 test files, 764 assertions, 0 failures
```

Image family gate:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -name 'PdfImage*Test.php' | sort)
20 test files, 1537 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
image_xobject_count=3
invoked_image_xobject_count=1
uninvoked_image_xobject_count=2
hidden_marked_invoked=false
hidden_object_optional_content_visible=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted image XObject fallback exclusion, Form XObject image-resource inheritance, ICC/Indexed/DeviceN/Calibrated color-space soft-mask previews, DCT/CCITT/JPX/JBIG2 preview-only boundaries, inline image payload boundaries, optional-content text/Form extraction, or xref/object-stream repair slices.

The bounded behavior here is specifically optional-content visibility applied to Image XObject review invocation counts before WordPress import.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, page resource resolver, optional-content visibility model, content token filter, stream decoders, and WordPress smoke renderer. Full upstream image raster parity remains dependency-gated by PDFium/pypdfium, Pillow image conversion, live OCR/layout models, and model-server paths; none were executed here.
