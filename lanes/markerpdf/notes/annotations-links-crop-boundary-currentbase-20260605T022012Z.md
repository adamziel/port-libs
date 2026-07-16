# Link Annotation Crop Boundary

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T022012Z`

Base accepted HEAD: `f639e74036a5a383ddac4c1be3eafa5976b5fa3c`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/pdf/extract_text.py` converts pdftext page output into Marker `Page` objects using each page bbox and rotation before downstream WordPress-style text conversion.
- Upstream `marker/pdf/images.py` renders pages with annotations disabled and crops via the page bbox. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py>
- Native no-GPU parity for this slice is therefore: annotation `/Rect` and `/QuadPoints` geometry remain review metadata, but WordPress span promotion is bounded by the effective page MediaBox/CropBox intersection and never executes PDF actions, JavaScript, Python models, PDFium, or external tools.

## Implementation

- `PdfLinkAnnotationExtractor` now intersects Link/Widget link `/Rect` values with the effective page bbox before promotion.
- Fully out-of-page link annotations are excluded from promoted link rows.
- Partially visible annotations keep raw `rect` and raw `quad_rects` review geometry, and add visible clipping metadata:
  - `visible_rect`
  - `pdftext_visible_rect`
  - `rect_inside_page_bbox`
  - `rect_clipped_to_page`
  - `visible_quad_rects`
  - `pdftext_visible_quad_rects`
  - `visible_quad_source_indexes`
  - `quad_rects_clipped_to_page`
  - `quad_rects_excluded_by_page_bbox`
- `applyLinksToPages()` now matches spans against visible page-bounded rect/quad candidates while preserving raw page rect metadata for review.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL clips Link annotation rectangles and QuadPoints to the visible page box before WordPress span promotion
The fully out-of-crop link annotation is excluded.
Expected: [7, 8, 10]
Actual: [7, 8, 9, 10]
1 test files, 4 assertions, 1 failures
```

## Focused Evidence

Syntax:

```text
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-crop-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-link-crop-boundary-currentbase.php
```

Focused assigned gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS clips Link annotation rectangles and QuadPoints to the visible page box before WordPress span promotion
1 test files, 41 assertions, 0 failures
```

Adjacent link/annotation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 882 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-crop-boundary-currentbase.php
```

The smoke emits `promoted_link_count=3`, `promoted_annotation_objects=[7,8,10]`, `partial_rect_clipped_to_page=true`, `partial_visible_rect=[50,200,134,218]`, `outside_link_excluded=true`, `margin_decoy_linked=false`, `destination_page=0`, `quad_rects_excluded_by_page_bbox=1`, `quad_outside_span_linked=false`, `visible_text_excludes_link_review_payloads=true`, and all PDF action, JavaScript, Python/model, and external-tool execution flags false.

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP behavior tests move `1289 -> 1290 pass / 0 fail`.
- WordPress scenarios move `1250 -> 1251`.
- Mapped upstream denominator is unchanged; this deepens the already mapped annotation/link geometry boundary.

## Non-Overlap

This does not repeat accepted URI extraction, local/remote GoTo actions, page-level `/Annots` ownership, escaped page `/Ann#6fts` names, exact-generation annotation references, Widget link inheritance, hidden/no-view filtering, rotated/UserUnit rect mapping, Link `/QuadPoints` parsing, Link presentation metadata, text-markup `/QuadPoints`, annotation appearance/popup/sound review, StructParent action context, outline target context, or xref repair boundaries.

The bounded behavior is specifically clipping Link annotation rectangles and QuadPoints to the effective page CropBox/MediaBox boundary before WordPress span promotion.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, generation-exact reference resolver, token-aware dictionary/array parser, inherited page geometry resolver, page bbox/rotation/UserUnit transform, supplied marker/pdftext span model, and Markdown span merge path. Full live upstream parity remains intentionally scoped out for Python/pdftext/pypdfium2/PDFium, Surya/Torch/model execution, OCR, and external raster/PDF tooling under the no-GPU markerPDF directive.
