# markerPDF Page Annotation Widget Link Current Base

Session: `port-dev-markerpdf-page40pdf-20260602T1855Z`
Micro-slice: `page-annotation-widget-link-currentbase`
Base accepted HEAD: `28240b72b0f77821c5ac2cf978b4d8bf8469270e`

## Source Truth

- Upstream Marker converts PDFs into page/block structures through the PDF converter pipeline and exposes rendered output for markdown/JSON consumers: https://github.com/datalab-to/marker
- PDF annotation dictionaries carry `/Subtype`, `/Rect`, and `/F` flags; the Hidden flag suppresses display and user interaction. The same annotation section defines Link and Widget annotation interaction boundaries: https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.2.pdf

This slice keeps the upstream boundary native and review-only: current page Widget annotation links may be attached to supplied pdftext spans, but PDF actions are not executed and Python/pdftext/pypdfium/Surya/model workers are not run.

## Implemented Behavior

- `PdfLinkAnnotationExtractor` now resolves indirect annotation `/Rect` arrays before link bbox extraction.
- Link-visible annotation flags now resolve indirect `/F` integer operands before hidden/no-view filtering.
- Page-scoped Widget URI and local-destination links with indirect geometry are promoted to supplied spans.
- Hidden or no-view Widget links with indirect flags remain excluded, and their URI payloads are not present in the linked page review payload.
- The change also applies the same hidden/no-view flag exclusion to ordinary Link annotations before promotion.

## Evidence

Syntax:

```text
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-widget-link-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-widget-link-currentbase.php
```

Focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/MarkdownPostProcessorTest.php
3 test files, 146 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-widget-link-currentbase.php
```

The smoke emitted `link_count=2`, `widget_link_count=2`, `uri_widget_href=https://example.com/indirect-widget`, `destination_widget_page=1`, `destination_widget_view_mode=FitH`, `indirect_hidden_widget_excluded=true`, `indirect_no_view_widget_excluded=true`, and all execution flags false.

JSON and whitespace:

```text
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

Both passed.

## Status Delta

- Behavior tests move `659 -> 660` pass / `0` fail.
- Mapped semantics move `482 -> 483 / 78`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted `/Subtype /Link` URI promotion, Widget link promotion with direct `/Rect` and `/F` operands, link/text-markup `/A` and `/AA` review, AcroForm widget appearance/action review, generic annotation popup/action review, or rich-media action boundaries. The new behavior is the indirect operand boundary for page-scoped Widget link geometry and visibility flags.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object scanning, page `/Annots` traversal, action review, name-tree destination resolution, bbox intersection, supplied pdftext spans, and Markdown span merging. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
