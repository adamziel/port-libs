# PageLabels Transitive Operands Current Base

Slice: `markerpdf-page-labels-boundary-current-base-20260605T023019Z`

Accepted base: `ca8ce650f8d6c127d28fd0204dd6d51033a95414`

## Source Truth

- Upstream markerPDF delegates searchable-PDF page text and preview page metadata to native PDF/pdftext behavior before higher-level conversion.
- PDF catalog `/PageLabels` is a number tree; `/Nums`, `/Kids`, `/Limits`, and label dictionaries may be direct or indirect PDF objects.
- This no-GPU slice stays inside native searchable-PDF parser behavior. It does not run OCR, Surya, Texify, Torch, pypdfium, Python, or external PDF tools.

## Behavior

Before this patch, a PDF with transitive indirect PageLabels operands could produce correct native text labels while `MarkerAppPreview` fell back to numeric page labels. The red probe used:

- `/PageLabels 20 0 R`
- `/Nums 29 0 R`, where `29 0 obj` points to `30 0 R`
- label dictionary operands `/P`, `/S`, and `/St` that each point through a second indirect object before their string/name/integer value

The probe returned text labels `['Front iv', 'Front v', 'App-Z']` and preview labels `['1', '2', '3']`.

The patch resolves PageLabels arrays and dictionaries recursively with cycle guards in both `PdfTextExtractor` and `MarkerAppPreview`, preserving generation-exact object lookup and keeping WordPress page-break metadata aligned with preview metadata.

## Evidence

- Focused baseline before patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php` => 1 test file / 70 assertions / 0 failures.
- Focused after patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php` => 1 test file / 76 assertions / 0 failures.
- Adjacent after patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionPageLabelStructureCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` => 5 test files / 1080 assertions / 0 failures.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-page-labels-transitive-operands-currentbase.php` emits matching `page_labels` and `preview_page_labels` of `['Nested Front iv', 'Nested Front v', 'Nested App-Z']`, `selected_preview_page_label='Nested App-Z'`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PDF object parser, page-label number-tree handling, and MarkerAppPreview summary path. GPU/model execution remains intentionally out of scope for markerPDF under the current no-GPU direction.

## Next Task

Continue with non-overlapping native PDF parser/converter behavior: searchable text extraction, fonts/CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, security preflight, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
