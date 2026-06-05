# MarkerAppPreview classic xref boundary current-base slice

Date: 2026-06-05 UTC

Accepted base: `edeac0d59e2932eecdef96341078d50d2caa9227`

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T073519Z`

## Behavior

`MarkerAppPreview` now applies the same token-boundary discipline used by the native classic xref rebuild path before choosing a preview catalog root. Preview catalog selection skips:

- commented `startxref` tokens;
- commented `trailer` tokens;
- trailer/startxref tokens inside arrays, dictionaries, literal strings, hex strings, or names;
- trailer/startxref tokens inside direct object bodies.

This keeps WordPress preview page count, PageLabels, CropBox/Rotate/UserUnit geometry, and rendered image sizing on the current rebuilt classic xref table when a later commented `startxref` points at a stale decoy catalog.

## Red-first evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewClassicXrefBoundaryCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`

Failure: the preview selected the later commented `startxref` decoy and reported 2 pages instead of the current rebuilt 1 page.

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewClassicXrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php`

Result: `4 test files, 608 assertions, 0 failures`

`php lanes/markerpdf/examples/wordpress-marker-app-preview-classic-xref-boundary-currentbase.php`

Result: emitted `current_preview_root_selected=true`, `current_page_label_selected=true`, `current_geometry_selected=true`, `decoy_preview_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint passed for:

- `lanes/markerpdf/src/MarkerAppPreview.php`
- `lanes/markerpdf/tests/MarkerAppPreviewClassicXrefBoundaryCurrentBaseTest.php`
- `lanes/markerpdf/examples/wordpress-marker-app-preview-classic-xref-boundary-currentbase.php`

`git diff --check -- lanes/markerpdf`

Result: no whitespace errors.

## Non-overlap

This does not change OCR, Surya/Texify/Torch, model workers, rasterization, or external PDF tools. It also does not reopen accepted classic xref extraction behavior; it applies the existing native boundary rules to the WordPress preview/root-selection path.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP PDF token scanning and balanced array/dictionary/string readers already present in `MarkerAppPreview`.

## Next

Continue no-GPU markerPDF work on non-overlapping native searchable-PDF behavior: annotations/forms/security preflight, images/filter metadata, font/CMap/text operators, metadata/outlines, xref repair, or supplied-boundary table/equation handoffs.
