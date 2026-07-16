# markerPDF Image XObject Alternate Wrapper Boundary Current Base

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260607T143342Z`

Base accepted HEAD: `d30a47d3f1909bba68426d3e20e0f67927b5f01d`

## Source Truth

Upstream markerPDF keeps searchable PDF text extraction separate from image rendering. Image XObjects and their alternate image streams flow through the image media handoff (`marker.pdf.images.render_image`) rather than becoming visible text.

Under the current no-GPU markerPDF lane scope, the PHP port owns the native parser and review boundary before any future raster backend. This slice does not run PDFium, PIL, OCR, Torch, Surya/Texify, models, or external PDF tools.

## Behavior

`PdfTextExtractor::imageXObjectAlternateImageReviews()` already resolved an indirect `/Alternates` array and exact-generation direct `/Image` references. The remaining boundary was an alternate dictionary whose `/Image` operand points at a wrapper object, and whose `/DefaultForPrinting` boolean is indirect:

- primary image object `5 0 R` has `/Alternates 8 0 R`;
- indirect array object `8 0 R` contains `<< /Image 9 0 R /DefaultForPrinting 12 0 R >>`;
- wrapper object `9 0 R` contains `6 1 R`, selecting the current alternate image generation;
- stale same-number alternate object `6 0 R` has different decoded bytes and is excluded;
- cyclic wrapper object `13 0 R` contains `13 0 R` and is skipped fail-closed.

The review row now selects alternate image generation `6 1`, records the indirect `default_for_printing=true` flag, preserves FlateDecode metadata/hash, excludes stale and cyclic alternate rows, and keeps primary/current/stale raster payload bytes out of visible WordPress text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAlternateWrapperBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves alternate image wrapper operands before review-only media handoff (lanes/markerpdf/tests/PdfImageXObjectAlternateWrapperBoundaryCurrentBaseTest.php)
Wrapped alternate image references resolve to one review row.
Expected: 1
Actual: 0

1 test files, 9 assertions, 1 failures
```

## Verification

Focused wrapper test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAlternateWrapperBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves alternate image wrapper operands before review-only media handoff

1 test files, 25 assertions, 0 failures
```

Adjacent image/alternate family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAlternateWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectAlternatesBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1327 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-alternate-wrapper-currentbase.php
```

The smoke exits `0` and emits `alternate_image_count=1`, `alternate_object=6`, `alternate_generation=1`, `indirect_default_for_printing=true`, `current_alternate_hash_selected=true`, `stale_alternate_hash_excluded=true`, `alternate_payload_excluded_from_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax and status checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectAlternateWrapperBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectAlternateWrapperBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-alternate-wrapper-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-alternate-wrapper-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` passed with no output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct `/Alternates` review, indirect `/Alternates` array resolution, DCT alternate-image EOI clipping, nested CCITT alternate metadata, exact-generation direct `/Alternates /Image` references, page/Form XObject resource-entry wrappers, duplicate XObject resource-name rejection, placement/CTM review, optional-content image visibility, masks/SMask/metadata/OPI review, Type3 CharProc image review, pattern image review, or primary Image XObject payload exclusion.

The bounded behavior is only the alternate dictionary operand boundary: `/Alternates` is already readable, but `/Image` may resolve through a wrapper object and `/DefaultForPrinting` may be an indirect boolean before the existing review-only alternate image handoff.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF array parser, exact-reference wrapper resolver, indirect boolean resolver, stream filter decoder, image XObject review rows, focused PHP tests, and a WordPress smoke. Full live raster parity remains gated on a future native raster/PDFium/PIL handoff, and OCR/model execution remains intentionally out of scope.
