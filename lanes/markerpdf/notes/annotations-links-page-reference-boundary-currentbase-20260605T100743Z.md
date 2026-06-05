## markerpdf annotations-links page-reference boundary current base

Slice: `markerpdf-annotations-links-boundary-current-base-20260605T100048Z`
Base accepted HEAD: `3588f117068107a96d624d26db10c2343396bc79`

### Source truth

- Upstream marker/markerPDF conversion is expected to preserve document links and references in structured output without executing PDF actions or requiring live model execution for searchable-PDF annotation review. Current upstream source reference remains the lane manifest entry for `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- ISO/PDF source truth: page objects own an `/Annots` array, while annotation dictionaries can carry `/P` as the associated page reference. For native import review, a page `/Annots` entry whose explicit `/P` names a different page object is treated as a cross-page corruption/decoy and is not promoted on the referencing page.

### Implemented behavior

- `PdfAnnotationExtractor`, `PdfLinkAnnotationExtractor`, and `PdfMarkupAnnotationExtractor` now pass the current page object number into annotation collection.
- Annotation dictionaries are accepted when `/P` is absent or when `/P` references the same page object. Mismatched `/P` references are rejected for that page before link promotion, markup application, popup/review extraction, or WordPress span decoration.
- The behavior preserves existing page `/Annots` ownership for PDFs that omit `/P`, and keeps page-two link/markup annotations available when page two also lists them.

### Red/green evidence

- Red before source fix:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkPageReferenceBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 2 assertions, 1 failures`
  - Failure: page one annotation objects were `[7, 8, 9, 10]`; expected `[7, 9]`.
- Focused pass after fix:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkPageReferenceBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 38 assertions, 0 failures`
- Adjacent annotation/link family pass:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotation*Test.php lanes/markerpdf/tests/PdfPageAnnots*Test.php lanes/markerpdf/tests/PdfPageAnnotation*Test.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php`
  - Result: `15 test files, 931 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-annotation-link-page-reference-boundary-currentbase.php`
  - Result: exits `0`; summary reports page-one annotation objects `[7,9]`, page-two annotation objects `[8,10]`, `cross_page_uri_excluded_from_page_one=true`, `cross_page_markup_excluded_from_page_one=true`, and no Python/models/external PDF tools/action execution.

### Dependency closure

No new support component is needed. This reuses the existing native PDF object scanner, dictionary tokenizer, annotation review extractors, link span promotion, and supplied WordPress smoke pattern. GPU/model/OCR execution remains intentionally out of scope for markerPDF under the current no-GPU directive.

### Next task

Continue with non-overlapping native searchable-PDF parser behavior: annotation/action edge cases, forms, page geometry, metadata, image/filter metadata, xref repair, or supplied-boundary table/equation handoffs.
