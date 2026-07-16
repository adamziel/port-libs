# markerPDF Image XObject Page Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260605T064148Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T064148Z`
Base accepted HEAD: `9c915aabae271ed83600146ffbbe977b2565f82c`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps text extraction and image rendering on separate paths:

- `marker/pdf/extract_text.py` obtains searchable text through `pdftext.extraction.dictionary_output()` and PDFium text pages.
- `marker/pdf/images.py` renders page and bbox crops through PDFium, disables annotation drawing, converts rendered images to RGB, and returns image data outside the text pipeline.

Source URLs inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now seeds image XObject placement scanning with the inherited effective page boundary when a page tree supplies explicit `/MediaBox` or `/CropBox` values:

- inherits page boxes from `/Pages` ancestors and resolves indirect numeric box operands;
- clips `/CropBox` to `/MediaBox`, matching the existing marker-app preview boundary semantics;
- applies that page boundary to Image XObject `Do` invocations, including nested Form XObject images;
- records page media/crop/clip boxes, clip source, media intersection state, page-clip reduction, and page-clip exclusion metadata;
- keeps outside-page and partially cropped image stream payloads out of visible WordPress text and review JSON.

The WordPress smoke emits two paragraph blocks plus a metadata comment proving a partial Image XObject is clipped to the visible page box, an outside-page image is unpainted, and a nested Form XObject image inherits the page boundary.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL clips image XObject placement to inherited page box boundaries
PHP Warning:  Undefined array key "page_media_box" ...
1 test files, 492 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-page-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-page-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS clips image XObject placement to inherited page box boundaries
1 test files, 529 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-page-boundary-currentbase.php
```

The smoke exits 0 and reports `page_clip_bbox=[0,20,150,160]`, `page_clip_source="crop_box_clipped_to_media_box"`, `partial_image_visible_bbox=[120,120,150,160]`, `outside_page_clip_excludes_image=true`, `nested_image_visible_bbox=[130,140,150,160]`, `payload_in_visible_text=false`, and both execution flags false.

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exits 0 with no whitespace errors.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused test growth: `PdfImageXObjectBoundaryCurrentBaseTest.php` grew from `488` to `529` assertions after the accepted ExtGState slice, adding one focused PASS case.
- PHP pass count: `1536 -> 1537`.
- WordPress scenario count: `1431 -> 1432`.
- Mapped upstream denominator: unchanged; this refines the already mapped Image XObject rendering/review boundary.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, content clipping paths, optional content, exact object generation, SMask/Mask image stream metadata, alternates, metadata streams, named ColorSpace resources, top-level XObject dictionary parsing, ExtGState transparency review, inline-image parsing, DCT/CCITT/JPX/JBIG2 preview filters, or Form-resource image discovery. The bounded behavior is specifically inherited page-box clipping at Image XObject placement boundaries.

## Dependency Closure

No new support component is needed. This reuses native PDF object scanning, page-tree lineage, indirect numeric operand parsing, content-token scanning, Form XObject traversal, and existing image review rows. Full rendered pixel parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, Poppler, Ghostscript, and other external PDF tools were not run.
