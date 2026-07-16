# markerPDF annotation dictionary duplicate-key boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260606T231109Z`

Accepted base: `f685254b0778d68ce6aa741679af3d6e4e13f252`

## Source Truth

Upstream markerPDF routes searchable PDF annotation/link review through PDF parser/PDFium/pdftext boundaries before OCR/model fallback. Under the current no-GPU scope, the native PHP parser owns dictionary token selection before WordPress import. This slice keeps duplicate annotation dictionary keys bounded to the selected top-level annotation dictionary, selecting the last top-level value for current `/Subtype`, `/Rect`, `/F`, `/Contents`, `/C`, `/QuadPoints`, `/T`, `/Subj`, `/CA`, and `/Border` review fields while ignoring stale first-key URI/review payloads.

No Python, PDFium, OCR, Surya, Texify, Torch, browser rendering, JavaScript execution, PDF action execution, or external PDF tools are used.

## Implementation

- `PdfAnnotationExtractor` now uses the last top-level duplicate dictionary key for annotation value lookup and array-valued metadata.
- `PdfMarkupAnnotationExtractor` now uses token-aware last-key dictionary lookup for markup annotation fields, including string fields, before applying highlight review metadata to supplied WordPress spans.
- `PdfLinkAnnotationExtractor` already used token-aware last-key lookup; the new focused test keeps link promotion covered across the shared boundary.
- Added `PdfAnnotationDictionaryDuplicateKeyBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdf-annotation-dictionary-duplicate-key-currentbase.php`.

## Evidence

Red-first current-base probe before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationDictionaryDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses last top-level annotation dictionary keys before WordPress link and markup promotion
Values are not identical
Expected: array (
  0 => 'Link',
  1 => 'Highlight',
)
Actual: array (
  0 => 'Text',
  1 => 'Text',
)

1 test files, 3 assertions, 1 failures
```

Focused run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationDictionaryDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses last top-level annotation dictionary keys before WordPress link and markup promotion

1 test files, 42 assertions, 0 failures
```

Adjacent annotation/link focused run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationDictionaryDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotsDuplicateKeyLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationDuplicateActionKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMarkupAnnotationIndirectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationEscapedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRectOperandBoundaryCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
...
9 test files, 687 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-dictionary-duplicate-key-currentbase.php
```

The smoke emits current `Link` and `Highlight` annotation subtypes, promoted link object `7`, markup annotation object `8`, `stale_first_keys_excluded=true`, `stale_span_unlinked=true`, `annotation_payload_text_visible=false`, and native-only execution flags.

## Non-overlap

This does not repeat duplicate page `/Annots` key selection, duplicate action `/A` or action-dictionary keys, escaped annotation dictionary names, indirect markup operands, malformed link `/Rect` operands, indirect numeric link operands, page geometry rotation/UserUnit mapping, optional-content link visibility, xref free-annotation suppression, object-stream action recovery, or page/StructTree annotation context. The bounded behavior is only duplicate keys inside the selected top-level annotation dictionary.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object parser, dictionary token scanner, annotation/link/markup extractors, supplied pdftext page-span handoff, and Markdown postprocessor. GPU/model/OCR paths remain intentionally out of scope under the current markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
