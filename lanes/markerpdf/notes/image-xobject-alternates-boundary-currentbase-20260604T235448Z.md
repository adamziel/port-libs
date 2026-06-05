# markerPDF Image XObject Alternates Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260604T235448Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260604T235448Z`
Base accepted HEAD: `9c59f44e51624d9351527a0d44299a9d0c048b4a`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from image rendering:

- `marker/pdf/extract_text.py` routes searchable text through pdftext/PDFium page text.
- `marker/pdf/images.py` renders page imagery through PDFium/PIL and converts/crops RGB images.

Under the current no-GPU lane scope, the PHP port owns the native parser boundary before any future raster backend. PDF Image XObject `/Alternates` entries are alternate raster streams that a renderer may choose for print or screen contexts; they are not content-stream `Do` invocations and must not become Gutenberg paragraph text.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now records image-local `/Alternates` arrays as review-only metadata on the painted base Image XObject:

- Parses direct alternate dictionaries such as `<< /Image 6 0 R /DefaultForPrinting true >>`.
- Records alternate image object number, default-for-printing flag, dimensions, color-space family, bit depth, image-mask state, filter chain, preview-only filter list, raw length, native decoded length/hash when safe, and review-only/payload-excluded flags.
- Keeps alternates out of `image_xobject_count`, `invoked_image_xobject_count`, placement bboxes, visible text extraction, and serialized payload text.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning:  Undefined array key "alternate_image_count" .../PdfImageXObjectBoundaryCurrentBaseTest.php on line 525
FAIL records alternate image XObject streams as review-only metadata without extra painted invocations
Values are not identical
Expected: 2
Actual: NULL
1 test files, 208 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records alternate image XObject streams as review-only metadata without extra painted invocations
1 test files, 217 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 845 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
```

The smoke emitted `image_xobject_count=3`, `invoked_image_xobject_count=1`, `first_alternate_image_count=2`, `first_print_alternate_object=12`, `first_print_alternate_default_for_printing=true`, `first_print_alternate_decoded_with_current_filters=true`, `first_screen_alternate_object=13`, `first_screen_alternate_preview_only_filters=["JPXDecode"]`, `payload_in_visible_text=false`, and only the expected WordPress paragraph blocks.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused behavior tests: `1141 -> 1142` pass / `0` fail.
- Focused assertion count for `PdfImageXObjectBoundaryCurrentBaseTest.php`: red-first `208` assertions with `1` failure, then `217` assertions / `0` failures.
- WordPress scenario count: `1131 -> 1132`.
- Mapped upstream denominator: unchanged; this refines the existing `pdfImageXObjectBoundaryBehaviors` row.

## Non-Overlap

This does not repeat accepted page image resource review, nested Form XObject image discovery, resource-less Form inheritance, optional-content visibility, CTM placement, rectangular clipping/Form BBox intersection, image dictionary metadata streams, inline image parsing, DCT/CCITT/JPX/JBIG2 preview-only filter boundaries, soft-mask/color-space/Decode previews, or XMP document metadata extraction.

The bounded behavior here is specifically Image XObject `/Alternates` stream review before WordPress import.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, image resource resolver, array/dictionary token parser, stream dictionary parser, stream filter decoder, content-token text extractor, and WordPress smoke renderer. Full upstream raster parity remains dependency-gated by PDFium/pypdfium and PIL image conversion; live OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, Poppler, Ghostscript, and external PDF tools were not run.
