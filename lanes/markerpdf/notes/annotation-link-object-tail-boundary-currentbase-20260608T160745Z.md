# markerPDF annotation/link object-tail boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T160745Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF keeps page annotation/action review separate from visible page text. In this no-GPU PHP lane, native PDF parsing owns the searchable-PDF annotation boundary before WordPress link spans and text-markup review metadata are promoted.
- A referenced page annotation object must be a single top-level annotation value. An object body such as `<< /Type /Annot ... >> 11 0 R` has a valid-looking annotation dictionary followed by an extra top-level operand; this slice rejects that object instead of accepting the first dictionary and leaking a decoy Link action or markup review row.

## Implementation

- `PdfAnnotationExtractor` now rejects indirect annotation objects whose resolved object body has trailing top-level operands before generic page annotation review rows are built.
- `PdfLinkAnnotationExtractor` now applies the same single-value ownership check before Link annotations can promote URI/local-destination metadata onto supplied marker/pdftext spans.
- `PdfMarkupAnnotationExtractor` now applies the same boundary before text-markup QuadPoints review metadata can attach to WordPress spans.
- Added focused coverage for one clean Link, one tailed Link object, one tailed Highlight object, and one clean Highlight object. The clean annotations remain importable; tailed action, highlight, and extra note payloads are excluded from annotation review, link review, markup review, Markdown output, and visible text.
- Added WordPress smoke `wordpress-pdf-annotation-link-object-tail-boundary-currentbase.php`.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkObjectTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed indirect annotation objects before link and markup promotion (lanes/markerpdf/tests/PdfAnnotationLinkObjectTailBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 7,
  1 => 10,
)
Actual: array (
  0 => 7,
  1 => 8,
  2 => 9,
  3 => 10,
)

1 test files, 2 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkObjectTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed indirect annotation objects before link and markup promotion

1 test files, 27 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotation*BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotation*BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMarkupAnnotation*BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnots*LinkBoundaryCurrentBaseTest.php
Focused test run: 69 selected test files (root lock skipped)
69 test files, 2268 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-link-object-tail-boundary-currentbase.php
```

Passed and emitted `tailed_link_object_excluded=true`, `tailed_markup_object_excluded=true`, `tailed_object_tail_operands_excluded=true`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page `/Annots` duplicate-key, escaped-name, top-level, direct/indirect array-tail, page-reference, generation, indirect action-object tail, indirect subtype-tail, Rect/QuadPoints operand, optional-content, xref-free annotation, or object-stream annotation slices. The bounded behavior is specifically the single top-level value requirement for referenced annotation object bodies before annotation, Link, and markup promotion.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, top-level value ownership checks, page annotation extractors, Link span promotion, text-markup review application, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL raster rendering, Streamlit/FastAPI model workers, JavaScript/PDF action execution, decryption, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
