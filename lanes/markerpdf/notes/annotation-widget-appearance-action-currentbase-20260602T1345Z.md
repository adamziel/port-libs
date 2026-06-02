# markerPDF Annotation Widget Appearance/Action Boundary

Session: port-dev-markerpdf-annot14pdf-20260602T1345Z
Micro-slice: annotation-widget-appearance-action-currentbase-20260602T1345Z
Base accepted HEAD: 271c9eceecf3eaf771bfe898f8a0661a10792e2c

## Source Truth

- Upstream markerPDF `marker/pdf/extract_text.py` routes native page text through pdftext/PDFium page boundaries.
- Upstream markerPDF `marker/pdf/images.py::render_image()` renders pages with `draw_annots=False`, so annotation appearance rendering is not a text-import primitive.
- PDF widget annotations combine page annotation dictionaries, field dictionaries through `/Parent`, selected `/AP /N` appearance states, `/MK` appearance characteristics, annotation `/F` flags, and primary/additional `/A` and `/AA` actions. This slice keeps those as review metadata and does not execute actions, JavaScript, appearance streams, Python models, pypdfium/PIL, or external PDF tools.

Upstream references:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

## Implemented

- `PdfAnnotationExtractor` now adds a `widget` review block for current-page `/Subtype /Widget` annotations.
- The review block resolves parent field object chains, field name/type/current/default values, field flags, annotation flags and visibility, highlight mode, selected `/AP /N` state, stale appearance state, `/MK` colors/captions/rotation/text position/icons, and action counts.
- Primary `/A` and additional `/AA` action rows remain supplied by the existing bounded action reviewer, and widget metadata reports `executes_action=false`, `actions_are_review_only=true`, `executes_appearance_streams=false`, and `renders_appearance=false`.
- Hidden/no-view current-page widgets are still reviewed as page annotations, while detached field-only widget objects outside the page `/Annots` array are excluded from the page annotation boundary.
- WordPress smoke emits a review-only list and summary comment for current-page widget review metadata.

## Red-First Evidence

Before the production change, the focused gate failed on the new fixture because widget annotations did not expose a `widget` review block:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php`

Result: `1 test files, 199 assertions, 1 failures`; failure expected `page_annotation_widget`, actual `NULL`.

## Passing Evidence

- `php -l lanes/markerpdf/src/PdfAnnotationExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAnnotationExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-widget-appearance-action-boundary.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php` passed: `1 test files, 263 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php` passed: `3 test files, 913 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-widget-appearance-action-boundary.php` emitted `review_annotation_count=2`, `widget_review_count=2`, `approved_field_name=review.consent`, `selected_appearance_object=30`, `primary_action_safety=[review-uri,local-destination]`, `additional_action_safety=[blocked-javascript,hide-action-review]`, `hidden_widget_visibility=no_view`, `selected_appearance_text_imported=true`, `stale_appearance_excluded=true`, `action_payloads_excluded_from_visible_text=true`, and all execution flags false.

- `jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- `lane-status.json` `phpPass`: `511 -> 512`.
- `UPSTREAM_TEST_MANIFEST.json` mapped semantics: `359 -> 360`.
- New mapped inventory key: `mappedPdfWidgetAnnotationAppearanceActionBehaviors`.

## Non-Overlap

This does not repeat:

- `annotation-widget-link-currentbase-20260602T1324Z`: current-page Widget URI/destination link promotion.
- `annotation-action-appearance-popup-currentbase-20260602T1312Z`: standard annotation action and popup/appearance boundaries.
- `acroform-appearance-default-resources` and `acroform-appearance-value-action`: catalog AcroForm field-level appearance/value/action metadata.
- `annotation /AP /N appearance stream text extraction`: selected appearance stream text import remains in `PdfTextExtractor`; this slice only adds page annotation widget review metadata.

## Dependency Closure

No new support component is needed. The slice reuses native PHP PDF object parsing, page `/Annots` traversal, field-parent dictionary lookup, appearance summary extraction, color/action helpers, `PdfTextExtractor` appearance text boundaries, and the WordPress smoke harness. Full upstream markerPDF parity remains gated by Python/pdftext/pypdfium/model execution and is not activated by this micro-slice.
