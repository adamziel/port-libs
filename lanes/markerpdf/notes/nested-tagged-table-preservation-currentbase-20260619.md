# markerPDF Nested Tagged Table Preservation Current-Base Slice

Date: 2026-06-19
Bead: plib-tuzwg.1

## Scope

This slice preserves unambiguous nested `/Table` StructElem children instead of flattening them at parent table-cell boundaries.

## Behavior

- `PdfMetadataExtractor` now records StructElem parent/child object links, direct MCIDs, descendant MCIDs, and a `structure_tree.tagged_tables` summary for top-level and nested tables.
- `TaggedTableStructureExtractor` renders unambiguous top-level tagged tables to WordPress table block HTML while appending nested table HTML inside the parent cell.
- `PdfPagePropertyExtractor` exposes compact page-scoped `structure_tagged_tables` review metadata, including nested table counts.
- `SuppliedDocumentConverter` accepts supplied `tagged_tables` records and replaces matching legacy visible cell text blocks with a single semantic table block.

## Evidence

- Focused reduced fixture: `php tools/run-tests.php lanes/markerpdf/tests/PdfNestedTaggedTablePreservationCurrentBaseTest.php` => 1 test file / 44 assertions / 0 failures.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-nested-tagged-table-currentbase.php` emits a `<!-- wp:table -->` block with `data-markerpdf-nested-table="true"` and rejects the `CUSTOM_GLYPH_LEAK_SHOULD_NOT_RENDER` `/ActualText` sentinel from visible output.

## Fixture

The fixture uses a marked PDF with an outer table (`40 0 R`) and nested inner table (`48 0 R`) under parent data cell (`47 0 R`). It verifies:

- top-level table objects: `[40]`
- nested table objects: `[48]`
- parent-cell descendant MCIDs: `[3, 4, 5, 6, 7]`
- nested table MCIDs: `[4, 5, 6, 7]`
- WordPress output contains `<p>Review packet</p><table... data-markerpdf-nested-table="true">`
