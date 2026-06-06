# markerPDF Annotations Links Xref-Free Action Boundary

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260606T131033Z`

Accepted base: `eecc865658e5cd10e8284e626f10d8b8a1b3a078`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- In the native no-GPU PHP lane, searchable-PDF annotation actions are review metadata only. PDF xref free entries mark objects unavailable in the current document revision, so a page Link annotation whose `/A` points at a free indirect object must not promote that stale URI to a WordPress span.
- This ports the parser boundary without Python, pdftext, pypdfium2, OCR, Surya, Texify, Torch, Streamlit/FastAPI workers, JavaScript execution, or external PDF tools.

## Implementation

- `PdfActionReviewExtractor` now suppresses xref-free object numbers when building its fallback parsed object map for action resolution.
- Xref-stream-selected object definitions remain authoritative and are not additionally filtered by the lightweight free map, preserving the accepted duplicate-row behavior where a first current xref-stream row wins over a later duplicate free row.
- `PdfLinkAnnotationFreedActionBoundaryCurrentBaseTest.php` covers a current Link annotation plus a second Link annotation whose `/A 20 0 R` points at an object marked free in the current classic xref table.
- `wordpress-pdf-link-annotation-freed-action-currentbase.php` emits a Gutenberg-oriented smoke showing only the current direct URI is promoted while the freed action URI and chained JavaScript are absent from review rows and visible text.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationFreedActionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL suppresses xref-free indirect Link action objects before WordPress span promotion
Freed indirect action objects must not become review actions.
Expected: array (
)
Actual: array (
  0 => array (... 'uri' => 'https://stale.example.com/freed-action', 'action_object' => 20),
  1 => array (... 'action_type' => 'JavaScript', 'safety' => 'blocked-javascript', 'chained' => true),
)
1 test files, 5 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationFreedActionBoundaryCurrentBaseTest.php
1 test files, 24 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php
1 test files, 30 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf.*Action.*Test\.php$' | sort)
57 test files, 2979 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfLinkAnnotation.*Test\.php$' | sort)
34 test files, 1179 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAnnotation.*Test\.php$|/PdfPageAnnotation.*Test\.php$' | sort)
17 test files, 963 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-freed-action-currentbase.php
```

The smoke emits `free_action_object_marked=true`, `annotation_objects=[7,8]`, `annotation_action_counts=[1,0]`, `promoted_link_objects=[7]`, `link_uri=https://example.com/current-action-docs`, `freed_action_promoted=false`, `freed_javascript_reviewed=false`, `visible_text_imported=true`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2554 -> 2555`.
- `lane-status.json` `wordpressScenarios`: `2168 -> 2169`.
- Added 1 focused PASS case and 24 focused assertions.

## Non-Overlap

This does not repeat accepted page `/Annots` tokenization, escaped Link dictionary keys, indirect `/S` subtype resolution, primary action array/scalar rejection, object-stream action selection, previous URI metadata, optional-content visibility, widget field inheritance, link geometry, QuadPoints, generation-exact annotations, free annotation-object suppression, or xref-stream duplicate free-row repair. The bounded behavior is specifically classic xref-free indirect action-object suppression before Link annotation review and WordPress span promotion.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP xref free-object map, object parser, action reviewer, page annotation extractor, link span applier, text extractor, and Markdown postprocessor. GPU/model/OCR parity remains intentionally out of scope under the current markerPDF no-GPU directive.
