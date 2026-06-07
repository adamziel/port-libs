# markerpdf source-alias table geometry boundary current-base

Slice: `markerpdf-table-geometry-boundary-current-base-20260607T164357Z`

Base accepted HEAD: `abb3ced14b55bfdea423eba08f7837618dfb2917`

## Behavior

Saved supplied table sidecars and reviewed table adapters can replay page-image table geometry under source/original rectangle aliases instead of primary `bbox` fields. The native no-GPU table boundary now treats `source_rect`, `source_rectangle`, `source_box`, `source_bounds`, `source_bounding_box`, `original_bbox`, `original_rect`, `original_rectangle`, `original_box`, `original_bounds`, and `original_bounding_box` as fallback geometry inputs only when primary bbox/named/polygon fields are absent.

The canonical localized records still store `source_bbox`; `source_coordinate_source` records the alias that supplied the fallback. OCR grid-border conflict rows now capture the source label before writing canonical `source_bbox`, so alias provenance survives conflict localization too.

## Evidence

Red-first focused probe before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySourceAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 0 assertions, 2 failures`; both tests failed with `Table geometry entries must include a four-value bbox, named bbox fields, two-corner point fields, or four-corner polygon alias.`

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySourceAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 38 assertions, 0 failures`.

Table geometry family check:

`php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'TableGeometry*CurrentBaseTest.php' | sort) lanes/markerpdf/tests/TableRecognizerTest.php`

Result: `50 test files, 2416 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-table-source-alias-boundary-currentbase.php`

Result: emitted JSON with `coordinate_status=translated_to_table_crop`, `cell_source_coordinate_source=original_bbox`, `source_alias_geometry_preserved=true`, `offcrop_source_alias_cells_filtered=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff checks:

`php -l lanes/markerpdf/src/TableRecognizer.php`

`php -l lanes/markerpdf/tests/TableGeometrySourceAliasBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-table-source-alias-boundary-currentbase.php`

All reported no syntax errors.

`git diff --check -- lanes/markerpdf`

Result: no whitespace errors.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native supplied table recognition and formatting path; it does not run Surya, tabled models, OCR, Python, GPU/model code, raster analysis, or external PDF tools.

## Non-Overlap

This does not repeat the accepted `source_bbox`/`source_page_image_bbox` fallback slice, polygon aliases, endpoint order aliases, wrapped bbox aliases, normalized page-image coordinate handling, page-result image-bbox handling, assigned-band filtering, or detector source metadata. It only covers alternate source/original rectangle names at the supplied table geometry replay boundary.

## Next

Continue with non-overlapping native markerPDF supplied-boundary work: table/equation handoff geometry edge cases, searchable-PDF fonts/CMaps, stream filters, xref repair, metadata, annotations, forms, security preflight, page geometry, and image/filter metadata.
