# markerpdf annotations links boundary current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260604T111154Z`

Accepted base: `a4d34fe066c7faaaa55a2358a545144a69cf48db`

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The upstream conversion path delegates searchable PDF text extraction to pdftext/PDFium-style page readers and does not execute PDF annotation actions during import.
- PDF page dictionaries carry page-owned `/Annots`. Nested dictionaries such as `/PieceInfo` private metadata are review material and must not replace the page dictionary's top-level `/Annots` array for WordPress link or markup promotion.
- This slice maps that native no-GPU parser boundary: top-level page `/Annots` is authoritative for current Link, Highlight, and Text annotations; stale private nested `/Annots` stays review-only.

## Implementation

- `PdfAnnotationExtractor`, `PdfLinkAnnotationExtractor`, and `PdfMarkupAnnotationExtractor` now resolve `/Annots` through a top-level page dictionary reader before falling back to legacy scanning on malformed page bodies.
- `PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php` covers a page whose `/PieceInfo /Private` dictionary contains a stale nested `/Annots [8 0 R]` before the real top-level `/Annots [7 0 R 9 0 R 10 0 R]`.
- `wordpress-pdf-page-annots-top-level-link-boundary-currentbase.php` renders the current link and current highlight review while proving the private nested link is not promoted to the stale span.

## Red first

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses only top level page Annots before promoting links and markup review metadata
Expected: array (0 => 7, 1 => 9, 2 => 10)
Actual: array (0 => 8)
1 test files, 2 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php
6 test files, 596 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfMarkupAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-annots-top-level-link-boundary-currentbase.php
No syntax errors detected in all changed PHP files
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-annots-top-level-link-boundary-currentbase.php
annotation_objects=[7,9,10]
link_uri=https://example.com/current-docs
markup_review=Current highlight review
private_nested_annots_promoted=false
stale_span_linked=false
```

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted generic link URI extraction, destination action review, rotated/UserUnit link rectangles, widget-link promotion, text-markup QuadPoints geometry, annotation appearance/popup/sound review, StructParent/ParentTree action context, or DCTDecode image-filter boundaries. The new behavior is specifically page-level `/Annots` ownership when a nested private/review dictionary contains a decoy `/Annots` key before the real page annotation array.

## Dependency closure

No new support component is needed. This reuses the native object scanner, dictionary/array token readers, page annotation extractors, link-span application, text-markup review application, and Markdown span merge. Full live OCR/model/PDFium parity remains out of scope under the current no-GPU markerPDF directive and was not run.
