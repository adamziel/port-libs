# markerpdf pdftext dictionary core sort half-even boundary

Slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T224841Z`
Base accepted HEAD: `c992bb947324f7207d596c6abc6496ba6a35dd32`

## Source Truth

Upstream `pdftext.extraction.dictionary_output(..., sort=True)` calls
`pdftext.postprocessing.sort_blocks`, which groups blocks with
`round(block["bbox"][1] / tolerance) * tolerance` before sorting each group by
left x coordinate. Python 3 `round()` uses half-even ties. Native PHP
`round()` defaults to half-up, so exact half-tolerance y positions could be
assigned to the next vertical group and reverse the expected left-to-right
reading order.

This patch changes `PdfTextDocumentExtractor::sortDictionaryOutputBlocks()` to
use `PHP_ROUND_HALF_EVEN`, preserving upstream grouping for supplied
`pdftext.dictionary_output` caches without invoking Python, PDFium, OCR/models,
or external PDF tools.

## Verification

- `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php`
- `php -l lanes/markerpdf/tests/PdfTextDictionaryCoreSortHalfEvenBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-sort-half-even-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreSortHalfEvenBoundaryCurrentBaseTest.php`
  - `1 test files, 14 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCore*CurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`
  - `14 test files, 721 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-sort-half-even-currentbase.php`
  - exits `0`; visible WordPress text is `Left half-even tie Right column first in source Second row after tie group`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native
`pdf-text-dictionary-core` boundary and only aligns its sort rounding with
upstream Python/pdftext behavior.

## Non-Overlap

Avoided OCR, Surya/Texify/Torch/model execution, stream filters, xref repair,
outline metadata, annotations, forms, images, and layout-order supplied
artifact selection. The change is limited to supplied pdftext dictionary
`sort=true` block ordering.
