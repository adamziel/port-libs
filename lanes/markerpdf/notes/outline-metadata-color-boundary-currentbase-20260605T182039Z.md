# markerpdf outline metadata color boundary current base 2026-06-05

## Scope

Native no-GPU markerPDF outline metadata boundary. PDF outline item `/C` color metadata is accepted only when it is an RGB array with exactly three numeric operands. Malformed direct or indirect arrays with extra operands stay review-neutral while the outline row, destination, TOC row, and visible page text remain intact.

This maps the pypdfium/markerPDF outline review boundary into native PHP: outline bookmark metadata is review-only navigation metadata for WordPress import, not page body text, and no Python, model worker, PDFium, PIL, or external PDF tool is executed.

## Patch

- `PdfMetadataExtractor::documentOutlineColorRgb()` now rejects `/C` arrays whose operand count is not exactly three.
- `PdfOutlineExtractor::outlineColorRgb()` applies the same exact three-operand rule for navigation review rows.
- Added `PdfOutlineMetadataColorBoundaryCurrentBaseTest.php` covering document metadata, navigation review, TOC preservation, destination resolution, and visible-text exclusion.
- Added `wordpress-pdf-outline-metadata-color-boundary-currentbase.php` smoke for WordPress outline navigation review.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataColorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects outline text color arrays with extra operands in document metadata
FAIL applies outline color operand boundaries to navigation review and visible text
1 test files, 17 assertions, 2 failures
```

Focused after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataColorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects outline text color arrays with extra operands in document metadata
PASS applies outline color operand boundaries to navigation review and visible text
1 test files, 31 assertions, 0 failures
```

Adjacent outline metadata/navigation run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataColorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataPageLabelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataXrefOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataXrefStreamRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNavigationEofMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNameTreeActionStructureCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineStructureDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineStructureDestinationPageContextCurrentBaseTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 605 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-color-boundary-currentbase.php
emits markerpdf-outline-metadata-color-boundary-currentbase with malformed_direct_color_excluded=true, malformed_indirect_color_excluded=true, navigation_malformed_colors_excluded=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Dependency Closure

No new support component is needed. This reuses the existing native PDF dictionary, array, indirect-object, metadata, outline, destination, and visible-text extractors. GPU/model OCR, PDF raster rendering, pypdfium, PIL, Python, and external PDF tools remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted DCTDecode ColorTransform, outline Parent/Prev/Last/title, destination view, page operand, page-label, xref-owner, object-stream, action-chain, structure, or metadata stream boundaries. The only behavior changed is malformed outline `/C` color operand cardinality.
