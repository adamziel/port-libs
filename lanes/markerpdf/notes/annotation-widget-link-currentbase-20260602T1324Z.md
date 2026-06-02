# markerPDF Annotation Widget Link Boundary

Session: `port-dev-markerpdf-annot12pdf-20260602T1324Z`
Micro-slice: `annotation-widget-link-currentbase-20260602T1324Z`
Base accepted HEAD: `8222e6d278bf50a168a1fbef8aa9e27f100cc5f3`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py::get_text_blocks` and `naive_get_text`, backed by pdftext/pypdfium text pages. `marker/pdf/images.py::render_image` renders pages with `draw_annots=False`. This native PHP slice keeps that boundary: annotation actions do not execute or render, but current page annotation metadata can be attached to already supplied pdftext spans for WordPress review/import.

Relevant PDF parser behavior for this slice:

- `/Subtype /Widget` is a page annotation subtype. A current page `/Annots` widget can carry a primary `/A` action or direct `/Dest` like other annotations.
- Widget activation and additional actions are review-only import metadata; safe URI actions may become WordPress links, while local destinations remain non-click-executing navigation metadata.
- Annotation flags `/F` invisible, hidden, and no-view mean the widget should not be promoted to visible text-span links.
- Widgets present only through AcroForm field trees, but not in the current page `/Annots`, are detached from the page text boundary and must not become WordPress links.

## Implemented Behavior

- `PdfLinkAnnotationExtractor` now accepts visible current page `/Subtype /Widget` annotations when the existing `PdfActionReviewExtractor` classifies the primary action as `review-uri`, `local-destination`, or `remote-document-review`.
- Link rows now include `annotation_subtype` and `widget_annotation`; applied spans receive `link_annotation_subtype` and `link_widget_annotation`.
- Hidden/no-view widgets and detached field-only widgets are excluded from link promotion.
- Added `wordpress-pdf-widget-link-annotation-import.php` to show a Widget URI link, a Widget local destination span, and hidden/detached widget exclusion in Gutenberg-oriented output.

## Evidence

Focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php
1 test files, 81 assertions, 0 failures
```

Adjacent annotation/form action gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/MarkdownPostProcessorTest.php
4 test files, 834 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-widget-link-annotation-import.php
```

The smoke emitted `link_count=2`, `widget_link_count=2`, `uri_widget_href=https://example.com/widget-docs`, `destination_widget_page=1`, `destination_widget_view_mode=FitH`, `hidden_widget_excluded=true`, `detached_widget_excluded=true`, and all execution flags false.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-widget-link-annotation-import.php
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

All passed.

## Status Delta

- Behavior tests move `507 -> 508`.
- Mapped markerPDF semantics move `355 -> 356 / 78`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted `/Subtype /Link` URI annotation promotion, link/text-markup destination and `/AA` review metadata, generic annotation popup/appearance/action review, AcroForm widget appearance/action review, or rich-media current-annotation action target boundaries. The new behavior is specifically current page `/Subtype /Widget` link-like actions becoming non-executing span metadata while hidden and detached widgets stay excluded.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, current page `/Annots` traversal, action reviewer, destination name-tree resolver, bbox intersection, supplied pdftext span model, and Markdown span merge path. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
