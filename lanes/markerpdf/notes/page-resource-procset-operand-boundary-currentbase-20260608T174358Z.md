# Page Resource ProcSet Operand Boundary Current Base

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF text extraction to parser/runtime layers before Marker block conversion. In the native no-GPU PHP lane, inherited page `/Resources` must preserve usable font resources for WordPress paragraphs while malformed resource metadata stays review-only and fail-closed. PDF resource `/ProcSet` is an array-valued resource entry; direct arrays or resolved array-wrapper objects with extra top-level operands are malformed for metadata promotion.

## Implementation

- Added `PdfPageResourceProcSetOperandBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdf-page-resource-procset-operand-boundary-currentbase.php`.
- Updated `PdfPagePropertyExtractor::procSetNames()` so inherited `/ProcSet` metadata rejects direct tailed operands and resolved array objects with tokens after the array.
- Kept the existing font resource lookup unchanged, so visible text extraction still uses inherited `/Font` resources from the same page-tree resource dictionaries.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceProcSetOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed inherited ProcSet operands before page-resource review metadata promotion
Expected: NULL
Actual: array (
  0 => 'PDF',
  1 => 'Text',
  2 => 'ImageB',
)
1 test files, 11 assertions, 1 failures
```

After fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceProcSetOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed inherited ProcSet operands before page-resource review metadata promotion
1 test files, 20 assertions, 0 failures
```

Adjacent resource-family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceProcSetInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceProcSetOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryObjectTailBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 75 assertions, 0 failures
```

## Non-Overlap

This does not repeat broad page resource inheritance, null resource inheritance, tree wrapper references, object-stream resource dictionaries, direct category tails, indirect category object tails, duplicate categories, entry-generation boundaries, or existing valid `/ProcSet` inheritance. The bounded behavior is only malformed direct and indirect `/ProcSet` array operands inside inherited page `/Resources` before page review metadata promotion.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, generation-aware object resolver, page-tree resource inheritance, page boundary metadata extractor, and WordPress smoke renderer. OCR, Surya/Texify/Torch model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF direction.
