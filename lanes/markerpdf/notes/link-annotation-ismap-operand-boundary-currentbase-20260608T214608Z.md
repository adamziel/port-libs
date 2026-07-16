# markerpdf Link annotation IsMap operand boundary current-base

Session: `port-dev-markerpdf-annotations-links-20260608T214608Z`
Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T214608Z`
Base accepted HEAD: `de56150306796ff6c39d1f6214abe62da3666962`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at
  `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Native markerPDF link extraction should surface searchable page text and safe
  static URI spans without executing PDF actions.
- PDF `/URI` action `/IsMap` is a boolean coordinate-dependent image-map
  signal. In the native no-GPU lane, indirect or malformed `/IsMap` operands
  must fail closed as review-only map links before WordPress span promotion.

## Implementation

- `PdfActionReviewExtractor` now reviews `/URI` action `/IsMap` operands with a
  dedicated boundary path before link promotion.
- Clean direct and indirect booleans preserve existing behavior:
  `/IsMap false` can promote a static URI, while `/IsMap true` remains
  coordinate-dependent review metadata.
- Malformed direct operands such as `/IsMap /Maybe`, and indirect booleans with
  trailing operands such as `true 30 0 R`, are classified as
  `coordinate-dependent-uri-review` with
  `uri_is_map_operand_malformed=true`.
- The review metadata records `uri_action_ismap_boolean_operand`,
  `review_only=true`, `payload_included=false`, `visible_text_source=false`,
  and `selected_value_policy=coordinate_dependent_review_for_malformed_boolean`.
- Added a WordPress smoke fixture proving only the clean static URI promotes to
  Markdown and malformed map payloads do not leak into visible text.

## Evidence

Focused IsMap operand boundary:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIsMapOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats malformed Link annotation IsMap operands as coordinate dependent before WordPress span promotion

1 test files, 32 assertions, 0 failures
```

Adjacent link-action boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIsMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationNewWindowOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRViewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS keeps coordinate dependent IsMap URI Link annotations review-only before WordPress span promotion
PASS resolves indirect Link action NewWindow booleans before WordPress review metadata promotion
PASS promotes only direct primary Link actions while keeping chained safe actions review-only
PASS keeps remote GoToR Link annotations as review metadata without local page promotion
PASS rejects malformed remote GoToR destination arrays before WordPress link promotion

5 test files, 192 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-ismap-operand-boundary-currentbase.php
exits 0 with promoted_link_objects=[7], action_safeties=[review-uri, coordinate-dependent-uri-review, coordinate-dependent-uri-review, coordinate-dependent-uri-review], malformed_ismap_operand_count=2, map_payload_promoted=false, visible_text_imported=true, executes_pdf_actions=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationIsMapOperandBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-annotation-ismap-operand-boundary-currentbase.php
No syntax errors detected
```

```text
git diff --check -- lanes/markerpdf
exits 0
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct `/IsMap true` coordinate-dependent URI
review, indirect `/NewWindow` boolean handling, remote GoToR review metadata,
remote GoToR destination-array rejection, chained safe action review, xref
repair, object-stream repair, outline extraction, form-field handling, OCR,
or model-worker behavior. The bounded behavior is only malformed and trailing
operand `/URI` action `/IsMap` boolean classification before WordPress link
promotion.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, dictionary parser, action review extractor, annotation summary path,
Markdown converter, and WordPress smoke fixture builder. Live OCR,
Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI
workers, JavaScript/PDF action execution, decryption/password validation,
signing/signature validation, and external PDF tools remain intentionally out
of scope under the no-GPU markerPDF directive.

## Next

Continue with a distinct no-GPU parser boundary in annotations, forms,
outlines, fonts/CMaps, stream filters, xref repair, page geometry,
image/filter metadata, or supplied-boundary table/equation handoffs.
