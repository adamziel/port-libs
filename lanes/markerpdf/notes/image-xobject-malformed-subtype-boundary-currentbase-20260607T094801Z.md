# markerPDF Image XObject Malformed Subtype Boundary

Session: `port-dev-markerpdf-image-xobject-20260607T094801Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260607T094801Z`
Base accepted HEAD: `89b8ba4aae1770f8a4893d04b0dafbf09afb50c6`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from image rendering:

- `marker/pdf/images.py` renders pages or bboxes through PDFium and converts the result to RGB.
- `marker/images/extract.py` inserts image Markdown from rendered image regions, outside visible text extraction.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

This native no-GPU PHP slice maps the parser-side boundary before any future raster backend: an explicit Image XObject `/Subtype` value is authoritative. If it is present but malformed, for example a literal string `(Image)` or an unresolved indirect reference, the stream must fail closed instead of falling back to `/Width` and `/Height` image heuristics. The dimension fallback remains available only when `/Subtype` is absent.

## Behavior

`PdfTextExtractor::isImageStreamDictionary()` now returns false for any explicit `/Subtype` that does not resolve to the name `/Image`. Missing `/Subtype` streams with image-like `/Width`, `/Height`, `/ColorSpace`, and `/BitsPerComponent` are still accepted for existing compatibility coverage.

The focused fixture proves:

- `/Subtype (Image)` is rejected and does not produce an image review row;
- `/Subtype 99 0 R` with no resolvable name is rejected;
- a sibling missing-subtype dimension fallback image remains reviewable and invoked;
- all raster payload bytes stay out of WordPress visible text and review JSON except decoded SHA-256 metadata for the accepted fallback image.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMalformedSubtypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects explicit malformed Image XObject subtype values before dimension fallback review
Values are not identical
Expected: 1
Actual: 3

1 test files, 4 assertions, 1 failures
```

The old parser counted the malformed explicit-subtype streams as Image XObjects through the dimension fallback.

## Verification

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMalformedSubtypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects explicit malformed Image XObject subtype values before dimension fallback review

1 test files, 34 assertions, 0 failures
```

Adjacent Image XObject subtype/dimension family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMalformedSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectExplicitSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectTopLevelDimensionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1385 assertions, 0 failures
```

Broader Image XObject/renderer family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 25 selected test files (root lock skipped)
25 test files, 2683 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-malformed-subtype-currentbase.php
```

The smoke exits 0 and emits `string_subtype_rejected=true`, `unresolved_subtype_rejected=true`, `missing_subtype_dimension_fallback_preserved=true`, `image_xobject_count=1`, `invoked_image_xobject_count=1`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/status/diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectMalformedSubtypeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-malformed-subtype-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All completed without errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, explicit non-Image subtype names, dimension validation, top-level dimension extraction, `Do` operand arity, `cm` operand arity, optional-content visibility, artifact suppression, path/page clipping, zero-area CTM suppression, zero-alpha ExtGState suppression, Form/Pattern/Type3 traversal, direct resource tail rejection, indirect BBox operand resolution, masks, metadata streams, OPI, filter metadata, inline-image tokenizer behavior, or live raster execution. The bounded behavior is only explicit malformed `/Subtype` values blocking the image dimension fallback.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary/name resolver, image stream classifier, Image XObject review collector, stream decoder, focused PHP tests, and WordPress smoke path. Full rendered-image parity remains gated on a future native raster backend; live OCR, Surya/Texify/Torch, GPU/model workers, PDFium/pypdfium/PIL execution, external PDF tools, and exact upstream model benchmark parity were not run.
