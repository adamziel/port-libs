# markerPDF Image XObject Form Resource Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260603T084923Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260603T084923Z`
Base accepted HEAD: `f0bd4183a2ffe1c741d3688a1bfed43e7facac09`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps page text extraction separate from PDF/image rendering. Page raster content painted through XObjects belongs to the image rendering boundary, not to visible text extraction.

This no-GPU native slice maps that boundary for images painted by invoked Form XObjects: the PHP parser now reviews nested image XObject metadata without rasterizing, running PDFium/PIL, launching Python models, or promoting image payload bytes into WordPress paragraphs.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now walks invoked Form XObject resources while reviewing page image XObjects:

- tokenizes page and form content streams for `Do` image/form invocations;
- multiplies nested invocation counts through the form stack;
- records nested image `resource_path`, `form_xobject_depth`, and `parent_form_xobject_object`;
- preserves existing page-level image review fields including filters, decoded length/hash, dimensions, soft mask, and payload text exclusion;
- uses an active Form XObject set to avoid cyclic resource walks.

`examples/wordpress-pdf-image-xobject-boundary-currentbase.php` now paints `/Hero Image` through `/Logo Form` and emits WordPress paragraph blocks plus review metadata proving the nested image payload stays out of visible text.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL maps image XObjects invoked inside Form XObject resources as review-only metadata
Expected: 2
Actual: 0
1 test files, 62 assertions, 1 failures
```

The pre-slice focused green baseline for the existing image XObject file was `1 test files, 61 assertions, 0 failures`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS maps inherited page image XObject resources as review-only current-base metadata
PASS keeps invoked image XObject payload bytes out of WordPress text extraction
PASS maps image XObjects invoked inside Form XObject resources as review-only metadata
PASS reports encrypted image XObject documents as fail-closed empty reviews

1 test files, 86 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
```

The smoke emitted non-empty WordPress block output with `first_resource_path=["Logo Form","Hero Image"]`, `first_parent_form_xobject_object=5`, `first_form_xobject_depth=1`, `first_image_filters=["ASCIIHexDecode","FlateDecode"]`, `first_image_decoded_with_current_filters=true`, and `payload_in_visible_text=false`.

## Status Delta

- Focused assertion count: `61 -> 86` (`+25`).
- Lane PASS cases: `992 -> 993`.
- WordPress scenario count: `992 -> 993`.
- Mapped upstream denominator: unchanged; this refines the existing `pdfImageXObjectBoundaryBehaviors` row.

## Non-Overlap

This does not repeat accepted page-level Image XObject review, inline-image payload exclusion, inline image filter-array abbreviation/null boundaries, ImageMask/soft-mask/ColorKey/JPX/JBIG2/DCT/CCITT color-space preview rows, Form XObject text extraction, annotation appearance Form XObject review, or stream-filter owner/xref repair slices. The new behavior is specifically image XObjects discovered through invoked Form XObject resource dictionaries.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, page resource lookup, content-token parser, stream decoder, image stream recognizer, and existing Form XObject stream decoder. Full raster parity remains gated on PDFium/PIL or a future native raster backend; this patch does not execute external PDF tooling, Python, OCR, Surya, Texify, Torch, or model workers.
