# markerPDF AcroForm Fields Overlap Boundary Current Base

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF text/form handling to PDF backends and keeps form activity outside visible Markdown text.
- PDF AcroForm field trees are rooted by the catalog `/AcroForm /Fields` array; malformed PDFs can repeat references or list both a parent and an owned child. WordPress review metadata should expose one terminal field row per selected field object, not duplicate rows for the same field value.

## Implementation

- `PdfAcroFormExtractor` now deduplicates terminal field rows by object number after `/Fields` and page-widget discovery but before submit/reset, signature, XFA, appearance, and action review annotations.
- The first selected traversal remains authoritative, so existing parent inheritance, widget ordering, generation checks, and page-owned widget repair behavior are preserved.
- New focused coverage builds a malformed AcroForm where `/Fields` contains `[6 0 R 10 0 R 10 0 R]`, with `10 0 R` also owned by parent `6 0 R`. Extraction now emits one `profile.email` row with inherited parent default metadata, terminal `/V` as authoritative review state, and widget `12 0 R` page context.
- The WordPress smoke confirms duplicate review rows are removed while form values remain review metadata and are not promoted to visible Gutenberg text.

## Verification

- Red probe before the source change:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsOverlapBoundaryCurrentBaseTest.php`
  Result: `1 test files, 2 assertions, 1 failures`; failure was `Expected: 1`, `Actual: 2` for the extracted field count.
- Focused test after patch:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsOverlapBoundaryCurrentBaseTest.php`
  Result: `1 test files, 30 assertions, 0 failures`.
- Adjacent field-boundary family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBranchRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsCycleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsOverlapBoundaryCurrentBaseTest.php`
  Result: `4 test files, 600 assertions, 0 failures`.
- Broader AcroForm family:
  `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort)`
  Result: `33 test files, 3052 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-overlap-boundary-currentbase.php`
  Result: emitted `field_count=1`, `duplicate_review_rows_removed=true`, `visible_text_contains_form_value=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This slice does not repeat accepted token-aware `/Fields` parsing, child root normalization, direct widget root normalization, page-owned widget repair, generation-exact scalar operands, branch repair, cycle boundaries, parent ownership rejection, submit/reset actions, signature locks, XFA packets, widget appearance states, or action-chain review. The bounded behavior is duplicate terminal field suppression when overlapping `/Fields` references reach the same owned field object.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware AcroForm field tree parser, page annotation widget map, field hierarchy/value review logic, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external PDF tools, action execution, form submission/reset execution, and signature validation remain intentionally out of scope for the no-GPU markerPDF lane.
