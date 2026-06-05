# markerPDF annotations links overlap-specificity boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T144132Z`
Session: `port-dev-markerpdf-annotations-links-20260605T144132Z`
Base accepted HEAD: `9bea7b4c06e1f594835627b0cfa11df5c9346166`

## Source truth

- Upstream `sddai/markerPDF` stays pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the current no-GPU markerPDF scope, searchable-PDF link annotation handling is native parser and span-promotion behavior before OCR/model handoff.
- PDF page `/Annots` may contain overlapping visible `/Subtype /Link` annotations. The WordPress importer must keep all valid annotation rows for review, but a broad earlier annotation rectangle must not swallow a later, narrower text-link rectangle or QuadPoints candidate when both intersect the same supplied pdftext span.
- PDF actions, JavaScript, live OCR, Python models, PDFium rendering, and external PDF tools are not executed.

## Implementation

- `PdfLinkAnnotationExtractor::linkForSpan()` now evaluates all intersecting link rectangle candidates instead of returning the first intersecting annotation.
- Candidate ranking prefers higher supplied-span coverage, then smaller candidate area, then larger intersection area, with original annotation/candidate order preserved only as a tie-breaker.
- This keeps broad page/banner annotations available for spans they uniquely own while letting narrower current `/Rect` or `/QuadPoints` links win their own text spans.
- Added `PdfLinkAnnotationOverlapSpecificityBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdf-link-overlap-specificity-currentbase.php`.

## Red first

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationOverlapSpecificityBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL chooses the most specific overlapping Link annotation before WordPress span promotion
The narrower current link must not be swallowed by the broad first annotation.
Expected: 'https://example.com/focused-docs'
Actual: 'https://example.com/broad-banner'
1 test files, 12 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationOverlapSpecificityBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS chooses the most specific overlapping Link annotation before WordPress span promotion
1 test files, 27 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationOverlapSpecificityBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationMalformedQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationFlagsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionScalarBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php
Focused test run: 15 selected test files (root lock skipped)
15 test files, 578 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfLinkAnnotation*Test.php' -o -name 'PdfAnnotationLink*Test.php' -o -name 'PdfPageAnnots*Test.php' -o -name 'PdfPageAnnotationWidgetLinkCurrentBaseTest.php' -o -name 'PdfPageWidgetFieldActionLinkCurrentBaseTest.php' \) | sort)
Focused test run: 32 selected test files (root lock skipped)
32 test files, 1142 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-link-overlap-specificity-currentbase.php
```

The smoke emits `annotation_objects=[7,8,9]`, `promoted_span_objects=[7,8,9]`, `focused_docs_not_swallowed_by_broad_link=true`, `sidebar_quad_not_swallowed_by_broad_link=true`, `sidebar_quad_index=0`, `annotation_payload_text_visible=false`, and all action/model/external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Status delta

- Focused PHP behavior cases: `2002 -> 2003`.
- WordPress scenarios: `1735 -> 1736`.
- Added 1 focused test file and 27 focused assertions for overlapping Link annotation span selection.

## Non-overlap

This does not repeat accepted page `/Annots` ownership, escaped annotation names, exact annotation object generation, exact page `/P` ownership, hidden/no-view flags, catalog `/URI /Base`, URI control bytes, name-tree `/Limits`, destination generation, remote GoToR, `/PA` previous URI review, primary `/A` shape gating, widget field inheritance, link presentation metadata, CropBox/rotation/UserUnit geometry, or malformed QuadPoints parsing.

The bounded behavior is only WordPress span-link selection when multiple valid current Link annotation rectangle candidates overlap the same supplied/pdftext span.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, page annotation traversal, action review resolver, link geometry transform, supplied pdftext span model, Markdown merge path, and WordPress smoke path. Live OCR, Surya/Torch/Texify models, pypdfium/PDFium rendering, PDF action execution, and external PDF tools remain intentionally out of scope for the no-GPU markerPDF lane.

## Next task

Continue with non-overlapping native searchable-PDF parser behavior around annotations/forms, fonts/CMaps, stream filters, xref repair, metadata, outlines, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
