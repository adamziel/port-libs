# markerPDF Link Annotation Additional-Action Operand Boundary

- Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T145124Z`
- Session: `port-dev-markerpdf-annotations-links-20260608T145124Z`
- Base accepted HEAD: `809a2da5c6b7ac8981b6fadaa6d9b301c311a0e2`
- Scope: native no-GPU markerPDF annotation/link parser behavior only.

## Source Truth

Upstream markerPDF keeps searchable-PDF text extraction and annotation metadata review separate from OCR/model stages, and PDF annotation actions must not execute during import. PDF Link annotations may carry `/AA` additional-action dictionaries for event review, but each event key must have one valid action value. A value such as `/E 21 0 R 22 0 R` is malformed and must not donate the referenced stale URI, JavaScript, Launch, or private tail payload into WordPress link review metadata.

This slice keeps safe primary `/A` URI promotion intact while failing closed for malformed `/AA` event operands. Clean sibling events such as `/U 23 0 R` are still reviewed, and malformed event rows remain visible as non-executing `malformed-action-dictionary` metadata.

## Red-First Evidence

Before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationAdditionalActionOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed Link annotation AA event operands without dropping primary URI links
Values are not identical
Expected: array (
  0 => 'malformed-action-dictionary',
  1 => 'review-uri',
)
Actual: array (
  0 => 'review-uri',
  1 => 'blocked-javascript',
  2 => 'review-uri',
)

1 test files, 7 assertions, 1 failures
```

The failing fixture had safe primary `/A` URI links plus malformed direct and indirect `/AA` cursor-enter operands. The accepted base followed the malformed `/E` action reference and exposed stale URI plus chained JavaScript review rows.

## Implementation

- `PdfActionReviewExtractor::additionalActionMetadata()` now inspects the resolved `/AA` dictionary for malformed event operands.
- Malformed event keys produce a review-only `malformed-action-dictionary` row with `malformed_action_operand_keys`, instead of walking the tailed action payload.
- Valid sibling additional-action events remain available as non-executing review metadata.
- Primary safe Link annotation `/A` URI promotion remains unchanged.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationAdditionalActionOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed Link annotation AA event operands without dropping primary URI links

1 test files, 46 assertions, 0 failures
```

Adjacent annotation/link family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfLinkAnnotation|PdfAnnotationLink|PdfAnnotationExtractor|PdfMarkupAnnotation|PdfPageAnnots|PdfPageAnnotationWidgetLink|PdfPageWidgetFieldActionLink).*Test\.php$' | sort)
Focused test run: 72 selected test files (root lock skipped)
72 test files, 2797 assertions, 0 failures
```

Shared action-review focused family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '(ActionReview|JavaScriptAction|AcroForm.*Action|Annotation.*Action).*Test\.php$' | sort)
Focused test run: 37 selected test files (root lock skipped)
37 test files, 1694 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-additional-action-operand-boundary-currentbase.php
```

The smoke exits `0` and emits `clean_additional_action_preserved=true`, `tailed_event_payloads_excluded=true`, `malformed_event_keys=[["E"],["E"]]`, all three primary links promoted, `visible_text_imported=true`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted valid `/PA` previous URI review, malformed `/PA` operand rejection, malformed nested `/A` operands for `/URI`, `/GoToR`, `/Launch`, and form actions, direct or indirect tailed `/A` and `/Dest` operands, duplicate action keys/subtypes, primary `/A` array/scalar rejection, catalog URI Base resolution, IsMap blocking, remote GoToR review, name-tree Limits, object-stream action selection, annotation `/Subtype` type guards, page `/Annots` ownership, optional-content filtering, QuadPoints geometry, widget field action inheritance, AcroForm inherited `/AA`, or xref/free annotation suppression.

The bounded behavior is only malformed Link annotation `/AA` event operands before additional-action review metadata attaches to WordPress spans.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, action reviewer, object-reference resolver, Link annotation extractor, supplied marker/pdftext page model, Markdown merge path, and WordPress smoke harness. Full live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, JavaScript/PDF action execution, media playback, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
