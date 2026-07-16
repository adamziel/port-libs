# Link Annotation QuadPoints Boundary

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T004014Z`

Base accepted HEAD: `7dc69d5aea3948399682b3467340c79f130a10f6`

## Source Truth

- Upstream markerPDF reaches link metadata through the PDF parser/PDFium/pdftext boundary before WordPress-style text conversion; link action execution is never needed for import.
- PDF Link annotations may carry `/QuadPoints` to describe one or more clickable quadrilateral text regions inside the broader required `/Rect`.
- Native no-GPU parity for this slice is therefore: parse `/QuadPoints`, keep them review-only as geometry metadata, use them for supplied marker/pdftext span intersection before falling back to `/Rect`, and do not execute PDF actions, JavaScript, Python, models, or external PDF tools.

## Implementation

- `PdfLinkAnnotationExtractor` now parses `/QuadPoints` arrays for `/Subtype /Link` and link-like visible widgets.
- Link rows retain:
  - raw `quad_points`
  - page-space `quad_rects`
  - transformed marker/pdftext `pdftext_quad_rects`
- `applyLinksToPages()` now prefers those quad rectangles when deciding whether a supplied span becomes a WordPress link.
- Span metadata records `link_quad_index`, `link_quad_rect`, `link_page_quad_rect`, and `link_pdftext_quad_rect` when a quad matched.
- Existing `/Rect` behavior remains the fallback for PDFs without valid link `/QuadPoints`.

## Focused Evidence

Syntax:

```sh
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-quadpoints-boundary-currentbase.php
```

Result: all report no syntax errors.

Focused assigned gate:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php
```

Result: `1 test files, 35 assertions, 0 failures`.

Focused link-family gate:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php
```

Result: `9 test files, 376 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-link-quadpoints-boundary-currentbase.php
```

Result: emits `quad_rect_count=2`, `middle_span_linked=false`, `visible_text_excludes_uri_payload=true`, and `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page-level `/Annots` ownership, escaped `/Annots` names, escaped Link dictionary keys, exact-generation link operands, Widget link promotion/inheritance, hidden/no-view widget filtering, rotated/UserUnit link `/Rect` mapping, text-markup `/QuadPoints`, annotation appearance/popup/sound review, StructParent action context, outline metadata owner selection, or xref repair boundaries.

The bounded behavior is specifically `/Subtype /Link` `/QuadPoints` geometry for WordPress span promotion.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, page annotation traversal, array/name/string parsing, page box/rotation/UserUnit transform, supplied marker/pdftext span model, and Markdown span merge path. Full upstream Python/PDFium/model benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
