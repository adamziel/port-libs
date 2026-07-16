# markerPDF Link annotation string operand boundary

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T172104Z`

Base accepted HEAD: `19e469ac5fba851474b6c82ad19f3b8c0f411282`

## Source truth

- Upstream `sddai/markerPDF` is pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- The no-GPU PHP port treats searchable PDF text and annotation metadata as parser boundaries before any OCR/model fallback. Link annotations may donate safe URI/destination metadata to WordPress spans, but annotation review strings must not leak stale operands or executable action payloads into import metadata.
- PDF dictionary entries have one value. A string value followed by another top-level operand before the next key is malformed for this parser boundary.

## Implementation

- `PdfLinkAnnotationExtractor::stringValueAfterName()` now rejects direct or indirect string values with trailing top-level operands before copying `/Contents`, `/T`, `/Subj`, `/NM`, or `/M` onto promoted link rows and WordPress spans.
- `PdfAnnotationExtractor::pdfStringValueAfterName()` now applies the same boundary for page annotation review rows.
- Clean direct and indirect strings still resolve, including UTF-16BE hex strings. Safe URI link promotion remains intact even when malformed string-state fields are dropped.

## Red-first evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationStringOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed Link annotation string state operands without dropping safe WordPress links
Direct tailed annotation string operands must stay review-malformed.
Expected: NULL
Actual: 'Tainted direct review'
1 test files, 7 assertions, 1 failures
```

After the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationStringOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed Link annotation string state operands without dropping safe WordPress links
1 test files, 77 assertions, 0 failures
```

Adjacent annotation/link family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationStringOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationStateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationDestinationOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationQuadPointsTailedOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 596 assertions, 0 failures
```

Full link-annotation regression family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfLinkAnnotation.*Test\.php$' | sort) lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
Focused test run: 54 selected test files (root lock skipped)
54 test files, 2176 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-link-string-operand-boundary-currentbase.php
```

The smoke exits 0 and emits `safe_link_targets_preserved=true`, `tailed_direct_state_dropped=true`, `tailed_indirect_state_dropped=true`, `tainted_review_text_excluded=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, generation-aware object lookup, dictionary and array value readers, PDF string decoder, annotation/link extractors, Markdown span merger, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium rendering, JavaScript/action execution, and external PDF tools remain intentionally out of scope.

## Non-overlap

This does not repeat accepted Link annotation state-field import, presentation operands, URI control safety, URI base handling, primary action selection, action arrays/scalars, previous URI review, destination operand validation, `/Rect` validation, `/QuadPoints` clipping or tailed operands, optional content filtering, object-stream link/action parsing, xref/free annotation repair, page-generation ownership, widget inheritance, or markup-reference chain slices.

The bounded behavior is specifically direct and indirect common annotation string-state operands with extra top-level tails.

## Next task

Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
