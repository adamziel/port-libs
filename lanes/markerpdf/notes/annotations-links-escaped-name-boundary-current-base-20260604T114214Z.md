# markerPDF Escaped Annots Link Boundary Current Base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260604T114214Z`

Accepted base: `0925d76072b425e14e69bc3935795bbde5e6004f`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The upstream conversion path delegates searchable PDF text extraction to pdftext/PDFium-style page readers and does not execute PDF annotation actions during import.
- PDF names can encode bytes with `#xx` escapes, so `/Ann#6fts` is the same page dictionary key as `/Annots`.
- Page-owned annotations are still the current annotation boundary. Nested private `/PieceInfo ... /Annots` dictionaries are review-only and must not supply WordPress links or markup review rows.

## Implementation

- `PdfLinkAnnotationExtractor` now decodes escaped key names while scanning the top-level page dictionary for `/Annots`.
- `PdfMarkupAnnotationExtractor` now uses the same escaped-name handling for text-markup annotation promotion.
- `PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php` covers a page with nested private `/Annots [8 0 R]` plus top-level escaped `/Ann#6fts [7 0 R 9 0 R 10 0 R]`.
- `wordpress-pdf-page-annots-escaped-name-link-boundary-currentbase.php` renders the current escaped-name link and highlight review while proving the stale private link is not promoted.

## Red First

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes escaped page Annots names before promoting link and markup annotations
Expected: 1
Actual: 0
1 test files, 5 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php
1 test files, 21 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php
7 test files, 617 assertions, 0 failures

php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfMarkupAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-annots-escaped-name-link-boundary-currentbase.php
No syntax errors detected in all changed PHP files

php lanes/markerpdf/examples/wordpress-pdf-page-annots-escaped-name-link-boundary-currentbase.php
annotation_objects=[7,9,10]
link_uri=https://example.com/escaped-docs
markup_review=Escaped highlight review
private_nested_annots_promoted=false
stale_span_linked=false
```

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP behavior tests move `1051 -> 1052 pass / 0 fail`.
- WordPress scenarios move `1051 -> 1052`.
- Mapped upstream denominator is unchanged; this is a deeper native PDF page dictionary name-decoding boundary under the already mapped annotation/link behavior.

## Dependency Closure

No new support component is needed. This reuses the native object scanner, dictionary/array token readers, annotation extractors, link-span application, text-markup review application, and Markdown span merge. Full live OCR/model/PDFium parity remains out of scope under the current no-GPU markerPDF directive and was not run.

## Non-Overlap

This does not repeat generic URI extraction, widget-link promotion, rotated/UserUnit link rectangles, text-markup QuadPoints geometry, annotation action review, or the earlier nested private `/Annots` top-level boundary for literal `/Annots`. The new behavior is specifically escaped page dictionary key decoding for `/Ann#6fts` before WordPress link and markup promotion.
