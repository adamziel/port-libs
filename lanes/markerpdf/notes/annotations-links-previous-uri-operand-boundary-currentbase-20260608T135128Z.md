# markerPDF Link Annotation Previous URI Operand Boundary

- Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T135128Z`
- Session: `port-dev-markerpdf-annotations-links-20260608T135128Z`
- Base accepted HEAD: `95ed9a719a03101e72b33de7de15d86db46d9a80`
- Scope: native no-GPU markerPDF annotation/link parser behavior only.

## Source Truth

Upstream markerPDF routes searchable-PDF text and annotation metadata through PDF parser/PDFium-style boundaries before OCR/model stages, and import must not execute annotation actions. PDF Link annotations may carry `/PA` previous URI action metadata for review, but malformed top-level operands such as `/PA 21 0 R 22 0 R` are not a single valid action value.

This slice keeps safe primary `/A` URI promotion intact while rejecting malformed `/PA` previous-action operands before they attach stale URI or JavaScript payloads to WordPress span review metadata. The tailed operand key remains visible through malformed action operand review metadata.

## Red-First Evidence

Before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed Link annotation PA previous action operands without dropping the primary URI link
A tailed /PA operand must not donate stale previous-URI review metadata.

1 test files, 5 assertions, 1 failures
```

The failing fixture had a safe primary `/A << /S /URI /URI (https://example.com/current-tailed) >>` plus malformed `/PA 21 0 R 22 0 R`. The accepted base promoted the primary link, but also attached `https://archive.example.com/tailed-previous-leak` as previous URI review metadata.

## Implementation

- `PdfActionReviewExtractor::reviewAnnotationActions()` now gates `/PA` through the same malformed dictionary-value checks already used by `/A` and `/Dest`.
- Resolved `/PA` values with object-level trailing operands are also ignored before previous URI review extraction.
- `PdfLinkAnnotationExtractor::applyLinksToPages()` now carries malformed action operand review keys to linked spans as `link_malformed_action_operand_*` metadata, matching link-row review state.
- The primary safe URI action remains promotable; only malformed previous-action payloads are excluded.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed Link annotation PA previous action operands without dropping the primary URI link

1 test files, 33 assertions, 0 failures
```

Adjacent annotation/link family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfLinkAnnotation|PdfAnnotationLink|PdfAnnotationExtractor|PdfMarkupAnnotation|PdfPageAnnots|PdfPageAnnotationWidgetLink|PdfPageWidgetFieldActionLink).*Test\.php$' | sort)
Focused test run: 71 selected test files (root lock skipped)
...
71 test files, 2751 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-previous-uri-operand-currentbase.php
```

The smoke exits `0` and emits `valid_previous_uri_preserved=true`, `tailed_previous_uri_excluded=true`, `primary_tailed_link_promoted=true`, `malformed_pa_keys=["PA"]`, `visible_text_imported=true`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted previous URI review for valid `/PA`, primary `/A` array/scalar rejection, direct or indirect tailed `/A` and `/Dest` operands, malformed nested action operands for `/URI`, `/GoToR`, `/Launch`, and form actions, duplicate action keys/subtypes, catalog URI Base resolution, IsMap blocking, remote GoToR review, name-tree Limits, object-stream action selection, annotation `/Subtype` type guards, page `/Annots` ownership, optional-content filtering, QuadPoints geometry, widget field action inheritance, or xref/free annotation suppression.

The bounded behavior is only malformed Link annotation `/PA` previous-action operands before previous URI review metadata attaches to WordPress spans.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, action reviewer, object-reference resolver, Link annotation extractor, supplied marker/pdftext page model, Markdown merge path, and WordPress smoke harness. Full live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, JavaScript/PDF action execution, media playback, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
