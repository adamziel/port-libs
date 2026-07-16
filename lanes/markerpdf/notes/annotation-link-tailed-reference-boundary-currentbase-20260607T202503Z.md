# markerPDF Annotation Link Tailed Reference Boundary

## Scope

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable PDF page text and PDF annotation metadata through the pdftext/PDFium boundary before any model/OCR work. Under the current no-GPU markerPDF scope, this slice maps a native PDF parser boundary for page `/Annots`: an indirect annotation carrier object must be a whole `object generation R` operand, not a malformed scalar with trailing top-level reference operands.

## Implementation

- `PdfAnnotationExtractor` now uses strict whole-value indirect-reference parsing when dereferencing page annotation values and reference-carrier annotation objects.
- `PdfLinkAnnotationExtractor` applies the same strict boundary before promoting Link annotations onto WordPress spans.
- The existing prefix-based indirect-reference helper remains available for contexts that intentionally scan operands inside arrays, dictionaries, and PDF value streams.

## Red-First Evidence

Before the implementation change, the focused test failed because malformed object `11 0 obj 8 0 R 9 0 R endobj` promoted object `8` as a real page annotation/link:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkTailedReferenceBoundaryCurrentBaseTest.php
FAIL rejects Annots references whose indirect object body has trailing top-level operands before link promotion
Expected: [7, 12]
Actual: [7, 8, 12]
1 test files, 2 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkTailedReferenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects Annots references whose indirect object body has trailing top-level operands before link promotion
1 test files, 24 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(AnnotationLink|LinkAnnotation|PageAnnots).*Test\.php$' | sort)
Focused test run: 55 selected test files (root lock skipped)
55 test files, 1860 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfAnnotationLinkTailedReferenceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-annotation-link-tailed-reference-boundary-currentbase.php
No syntax errors detected
```

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-link-tailed-reference-boundary-currentbase.php
tailed_reference_rejected=true
tailed_span_unlinked=true
annotation_payload_text_visible=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

```text
git diff --check -- lanes/markerpdf
no whitespace errors
```

## Status Delta

- New focused test file: `PdfAnnotationLinkTailedReferenceBoundaryCurrentBaseTest.php`.
- New WordPress smoke: `wordpress-pdf-annotation-link-tailed-reference-boundary-currentbase.php`.
- `phpPass`: `2923 -> 2924`.
- `wordpressScenarios`: `2435 -> 2436`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, page `/Annots` traversal, annotation review, link promotion, Markdown span merging, and text extraction paths. It does not run Python, pdftext, pypdfium/PDFium, Surya/Torch OCR/layout/table/equation models, JavaScript actions, raster rendering, external PDF tools, or live provider services.

## Non-Overlap

This does not repeat accepted link URI control-byte safety, duplicate action-key review, page `/P` generation ownership, indirect `/Annots` array flattening, object-stream link extraction, optional-content link visibility, QuadPoints geometry, named-destination resolution, outline metadata, AcroForm/widget action inheritance, xref/free-annotation repair, or markup page-reference boundaries. The bounded behavior is only tailed top-level operands in an indirect annotation reference carrier object before annotation review and WordPress link promotion.
