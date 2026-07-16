# markerpdf xref-stream Prev-chain AcroForm current-base slice

Date: 2026-06-08 UTC
Base accepted HEAD: `72ddd104de73563cbfd9ef3ec17976bf6afc1676`
Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T232831Z`

## Source Truth

The markerPDF upstream manifest records `sddai/markerPDF` as a Python PDF-to-Markdown pipeline using pdftext, pypdfium2, model-assisted layout/OCR, table/equation/image extraction, and Markdown post-processing. Under the current no-GPU markerPDF lane scope, this slice ports the native searchable-PDF parser boundary in PHP rather than launching upstream Python, PDFium, OCR, Surya/Texify/Torch, or model workers.

PDF 1.5 xref streams can be the latest `startxref` source in incremental updates. Their dictionary can carry `/Root` and `/Prev`, and their decoded `/W` rows plus `/Index` ranges identify current direct object offsets. AcroForm review must select field/widget/page dictionaries from that current xref chain before stale previous-section rows or unreferenced higher-generation direct objects discovered by fallback scanning.

## Implementation

`PdfAcroFormExtractor` now uses a shared xref-selected object path for both classic xref tables and direct xref streams. The xref-stream path decodes direct `FlateDecode` xref stream rows, supports `/W`, `/Index`, and `/Prev`, merges current rows over previous rows, and reads `/Root` from either classic trailers or xref-stream dictionaries before resolving the catalog AcroForm. Type-1 direct rows select current field/widget/page objects; non-type-1 rows suppress previous rows for the same object but are not expanded into AcroForm object-stream members in this slice.

The red-first fixture had a previous classic xref section, a current xref stream with `/Prev`, and unreferenced generation-2 direct decoys. Before the patch, AcroForm review selected `decoy.prev.email` with stale form metadata. After the patch it selects `current.prev.email`, `NeedAppearances=true`, widget object `8`, page object `3`, and current visible page text.

## Verification

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfXrefPrevChainAcroFormCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-acroform-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainAcroFormCurrentBaseTest.php` => 1 test file, 33 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsXrefGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainAcroFormCurrentBaseTest.php` => 4 test files, 708 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAcroFormFieldsXrefGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainAcroFormCurrentBaseTest.php` => 5 test files, 955 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-acroform-currentbase.php` => exits 0 with `current_field_selected=true`, `current_value_selected=true`, `need_appearances_selected=true`, `current_page_text_selected=true`, `stale_form_review_excluded=true`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat the accepted classic AcroForm xref-generation boundary, trailer-root boundary, object-stream field expansion, text/metadata/attachment xref-stream repairs, free-row suppression, omitted-row repair, damaged-offset repair, outline/action/page-review xref repairs, or OCR/model/PDFium runtime behavior. It only covers AcroForm review selection from a latest direct xref stream with a `/Prev` chain and current trailer `/Root`.

## Dependency Closure

No new support component is needed. The implementation reuses native PDF direct-object scanning, stream decoding, xref table parsing, xref stream decoding, AcroForm field/widget review, visible text extraction, and the WordPress smoke path. Remaining out-of-scope follow-up: AcroForm objects stored as xref-stream type-2 object-stream members and indirect xref-stream operand helpers beyond the direct-row boundary exercised here.
