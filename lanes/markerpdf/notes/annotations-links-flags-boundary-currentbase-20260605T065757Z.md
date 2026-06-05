# markerPDF annotations links flags boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T065757Z`
Session: `port-dev-markerpdf-annotations-links-20260605T065757Z`
Base accepted HEAD: `13a03f44f03f1a17e55a3c59df211c0698381848`

## Source truth

- Upstream markerPDF keeps link handling at the searchable-PDF extraction and Markdown post-processing boundary. The native PHP lane promotes safe Link annotations onto supplied/pdftext spans without executing PDF viewer actions, JavaScript, Python models, PDFium rendering, or external PDF tools.
- PDF annotations carry an `/F` bitmask. Invisible, hidden, and no-view bits suppress interactive promotion, while visible flags such as print, no_zoom, no_rotate, read_only, and locked remain useful import-review metadata for WordPress editors.

## Behavior

- `PdfAnnotationExtractor` now exposes `annotation_flags`, `annotation_flag_names`, and `annotation_visibility` on common page annotation review rows, not only nested widget metadata.
- `PdfLinkAnnotationExtractor` mirrors the same decoded flag metadata on promoted Link rows and supplied span fields:
  - `link_annotation_flags`
  - `link_annotation_flag_names`
  - `link_annotation_visibility`
- Existing invisible/hidden/no-view exclusion remains unchanged: no-view Link annotations are omitted from promoted link rows and cannot become WordPress hrefs.

## Evidence

Red-first focused command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationFlagsBoundaryCurrentBaseTest.php
```

Result before source edits: `1 test files, 3 assertions, 1 failures`.

Focused command after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationFlagsBoundaryCurrentBaseTest.php
```

Result: `1 test files, 38 assertions, 0 failures`.

Adjacent annotation/link command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationFlagsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
```

Result: `4 test files, 480 assertions, 0 failures`.

Broader link-family command:

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfLinkAnnotation|PdfPageAnnots.*LinkBoundary|PdfPageWidgetFieldActionLink|PdfPageAnnotationWidgetLink|PdfAnnotationLinkGenerationBoundary).*Test\.php$' | sort)
```

Result: `19 test files, 743 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-flags-currentbase.php
```

Result: emits `promoted_link_objects=[7,8]`,
`printable_link_flags=["print","no_zoom","no_rotate","read_only","locked"]`,
`printable_span_flags=["print","no_zoom","no_rotate","read_only","locked"]`,
`hidden_annotation_visibility="no_view"`, `hidden_no_view_promoted=false`,
`visible_text_excludes_link_flag_metadata=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint:

```bash
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationFlagsBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-annotation-flags-currentbase.php
```

Result: no syntax errors in all four changed PHP files.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted page `/Annots` ownership, escaped annotation-name
handling, exact object-generation link selection, indirect geometry/flag
visibility for widgets, URI control-byte filtering, catalog URI base resolution,
primary `/Next` chain gating, remote GoToR direct-primary review, link CropBox/
rotation/UserUnit geometry, QuadPoints clipping, widget-link inheritance, link
presentation `/H`/`C`/`BS`/`Border` metadata, or comment handling inside link
dictionaries. The bounded behavior is specifically visible Link annotation `/F`
flag review metadata on page annotation rows, promoted Link rows, and supplied
WordPress spans while preserving no-view/hidden exclusion.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner,
annotation extractor, action review parser, link span promoter, supplied
marker/pdftext span model, Markdown merge path, and WordPress smoke. Live
pdftext, pypdfium/PDFium, Surya/Torch/OCR/layout models, Texify, PDF action
execution, browser/viewer automation, and external PDF tools remain
intentionally out of scope for this no-GPU markerPDF slice.
