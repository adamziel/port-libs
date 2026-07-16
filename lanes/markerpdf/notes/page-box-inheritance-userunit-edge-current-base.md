# Page Box Inheritance UserUnit Edge

Slice: `markerpdf-page-box-inheritance-userunit-edge-current-base-20260602T0520Z`

Source-truth boundary:

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker_app.py` opens uploaded PDFs with `pypdfium2.PdfDocument`, counts pages with `len(doc)`, and renders previews with `page_indices=[page_num - 1]` and `scale=dpi / 72`.
- Adobe PDF Reference 1.7 Table 3.27 marks `MediaBox`, `CropBox`, `Resources`, and `Rotate` as inheritable page attributes. It also states non-inheritable attributes cannot be inherited. `UserUnit` is a positive page-dictionary value with default `1.0`, so this slice keeps it page-local.

Native PHP behavior added:

- `MarkerAppPreview` now ignores invalid non-multiple-of-90 page `/Rotate` values instead of allowing them to override a valid inherited `/Rotate`.
- The focused preview path keeps inherited indirect `/CropBox` metadata through page-tree traversal and preserves page-local `/UserUnit` scaling for rendered preview geometry.
- `wordpress-pdf-page-box-inheritance-userunit.php` demonstrates the WordPress review metadata: page 1 inherits `/CropBox` and `/Rotate` but ignores `/Pages /UserUnit`, while page 2 applies page-local `/UserUnit 2`.

Focused evidence:

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php` failed before the source fix with expected rotation `90`, actual `0`.
- Passing after implementation: `php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewTest.php` passed with 1 test file, 64 assertions, and 0 failures.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-page-box-inheritance-userunit.php` emitted inherited CropBox/Rotate metadata, default page-1 UserUnit, page-2 UserUnit-scaled render size, and native-only flags.
- Full focused lane: `php tools/run-tests.php lanes/markerpdf/tests` passed with 56 test files, 2024 assertions, and 0 failures.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PDF object scanner, page-tree walker, `MarkerAppPreview`, and `PdfImageRenderer`; full live preview parity remains gated on Streamlit, pypdfium2, PIL, and the upstream Python/model stack.
