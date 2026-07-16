# Layout Page Annotation StructTree Table Bundle Current Base

Slice: `layout-page-annotation-structtree-table-bundle-currentbase`

Session: `port-dev-markerpdf-layout69-20260602T215714Z`

Base accepted HEAD: `0059bb644ec3506849ecf93d4f87651501a9af5b`

## Source Truth

- Upstream markerPDF pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker/tables/table.py::get_table_boxes()` merges adjacent `Table` layout boxes, rescales them to high-resolution image coordinates, and returns table counts plus selected pdftext line payloads.
- Upstream markerPDF `marker/tables/table.py::format_tables()` replaces only intersecting `Table` blocks, leaves neighboring section/caption/text blocks in page order, and inserts the recognized Markdown table as a synthetic `Table` block.
- PDF structure-tree parser behavior is the dependency boundary for this PHP slice: page `/StructParents` maps through `/StructTreeRoot /ParentTree` arrays, while annotation `/StructParent` maps to singular ParentTree object entries/OBJR rows. Existing lane notes cite PDFium `CPDF_StructTree` as the parser source truth for that ParentTree boundary.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/tables/table.py
- https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7421/core/fpdfdoc/cpdf_structtree.cpp

## Implementation

- `SuppliedDocumentConverter` now accepts `page_review_metadata` as a supplied artifact, matches it to extracted pages by page object, `pnum`, or page index, and records the `page-review-metadata` supplied boundary.
- `TableFormatter` now projects review-only page context into each table context row when page review metadata is present:
  - page label/object `/StructParents` and page `/PieceInfo`;
  - page `structure_marked_content` rows from StructTree ParentTree MCIDs;
  - singular annotation `/StructParent` rows whose annotation `Rect`/quad geometry intersects the table page bbox;
  - text markup annotation rows whose geometry intersects the table page bbox.
- Annotation review rows outside the table geometry stay detached from the table context, so stale or unrelated annotation text is not bundled with a WordPress table.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLayoutPageAnnotationStructTreeTableBundleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bundles layout table review with page StructTree and overlapping annotation metadata
...
1 test files, 7 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLayoutPageAnnotationStructTreeTableBundleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS bundles layout table review with page StructTree and overlapping annotation metadata

1 test files, 39 assertions, 0 failures
```

Focused family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLayoutPageAnnotationStructTreeTableBundleCurrentBaseTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
...
4 test files, 571 assertions, 0 failures
```

Example smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-layout-page-annotation-structtree-table-bundle-currentbase.php
```

The smoke emitted `supplied_boundaries=["layout","page-review-metadata","table-recognition","table-formatting"]`, `page_label="bundle-4"`, `annotation_objects=[7]`, `annotation_struct_parents=[16]`, `annotation_associated_file="table-annotation-source.xml"`, `excluded_legacy_table_text=true`, `excluded_outside_annotation_context=true`, and no Python/model/external PDF tool execution.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/SuppliedDocumentConverter.php
No syntax errors detected in lanes/markerpdf/src/SuppliedDocumentConverter.php
php -l lanes/markerpdf/src/TableFormatter.php
No syntax errors detected in lanes/markerpdf/src/TableFormatter.php
php -l lanes/markerpdf/tests/PdfLayoutPageAnnotationStructTreeTableBundleCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfLayoutPageAnnotationStructTreeTableBundleCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-layout-page-annotation-structtree-table-bundle-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-layout-page-annotation-structtree-table-bundle-currentbase.php
jq . lanes/markerpdf/lane-status.json >/dev/null
git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` produced no output.

## Non-Overlap

This does not repeat accepted standalone page `/StructParents` ParentTree extraction, annotation `/StructParent` extraction, page `/PieceInfo` review rows, table OCR layout cell routing, table span-grid accessibility review, or forced-OCR merged table handling. The new behavior is the integration boundary where supplied table formatting carries page StructTree and overlapping annotation StructParent metadata into table-context review rows.

## Dependency Closure

No new support component is needed. This reuses the native supplied-document converter, table formatter, layout bbox rescaling, page review extractor, annotation StructParent review, ParentTree walker, and WordPress smoke path. Full live upstream parity remains dependency-gated on `pdftext`, `pypdfium2`/PDFium rendering, Surya/Torch layout/OCR models, tabled-pdf model execution, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
