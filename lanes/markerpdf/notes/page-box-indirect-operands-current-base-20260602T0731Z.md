# Page Box Indirect Operands

Slice: `markerpdf-page-box-userunit-rotation-edge-current-base-20260602T072821Z`

Source-truth boundary:

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker_app.py` opens uploaded PDFs with `pypdfium2.PdfDocument`, counts pages with `len(doc)`, and renders previews with `page_indices=[page_num - 1]` and `scale=dpi / 72`.
- ISO 32000-1 / PDF 1.7 page dictionaries define `MediaBox`, `CropBox`, and `Rotate` as page attributes that affect displayed page geometry; `UserUnit` changes the default user-space scale for that page. PDF indirect objects may be referenced as array elements, so a rectangle array can contain numeric indirect references that a conforming reader resolves before rendering.

Native PHP behavior added:

- `MarkerAppPreview` now resolves indirect numeric operands inside page-box rectangle arrays such as `/CropBox [4 0 R 5 0 R 6 0 R 7 0 R]`.
- Unresolved or nonnumeric rectangle operand references fail closed to the existing inherited/default box behavior instead of being misread as object/generation numbers.
- The focused preview path keeps the existing rotation and page-local `/UserUnit` behavior while applying the resolved rectangle dimensions to display, physical, and rendered-image sizes.

Focused evidence:

- Red-first probe on the current base produced a corrupt preview bbox `[4, 0, 5, 0]`, display size `0 x 1`, physical size `0 x 1.5`, and rendered size `0 x 2` for a direct `/CropBox` array whose four coordinates were indirect numeric objects.
- Passing after implementation: `php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php` passed with 1 test file, 78 assertions, and 0 failures.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-page-box-indirect-operands.php` emitted `indirect_rectangle_operands_resolved=true`, page bbox `[25,35,325,435]`, rotation `270`, UserUnit `1.5`, and rendered size `600 x 450`.
- Supervisor full-lane gate after applying on current integration base `391dc576f`: `php tools/run-tests.php lanes/markerpdf/tests` passed with 59 test files, 2589 assertions, and 0 failures.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PDF object scanner, page-tree walker, `MarkerAppPreview`, and `PdfImageRenderer`; full live preview parity remains gated on Streamlit, pypdfium2, PIL, and the upstream Python/model stack.

Non-overlap:

- This does not repeat accepted page-box inheritance, page-local `/UserUnit`, invalid page-local rotation fallback, text-markup QuadPoint rotation, annotation geometry, or pdftext dictionary rotation slices. The new behavior is specifically resolving indirect numeric operands inside direct page-box rectangle arrays before WordPress preview sizing.
