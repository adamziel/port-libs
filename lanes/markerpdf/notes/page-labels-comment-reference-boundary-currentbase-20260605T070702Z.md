# markerPDF PageLabels comment reference boundary

## Source truth

- Upstream markerPDF gets page-local text and document structure through PDF page iteration; native PHP PageLabels remain page-break/review metadata and do not alter visible text.
- PDF comments are token whitespace. Catalog `/PageLabels` number-tree references, `/Kids` references, `/Nums` value dictionaries, and page-label dictionary scalar operands can therefore be separated by `% ... EOL` comments without changing the referenced object number/generation.
- Relevant parser behavior: pypdf documents page labels as a PDF `/PageLabels` number tree and cites PDF 1.7/2.0 section 12.4.2; the `/Nums` array stores integer keys with corresponding label dictionaries, and indirect values are dereferenced before labels are generated.

## Implementation

- `PdfTextExtractor` now has a comment-aware indirect-reference token reader used by PDF value scanning, array tokenization, and PageLabels exact-generation reference resolution.
- `MarkerAppPreview` now mirrors the same comment-aware reference parsing in fallback catalog inventory and PageLabels recursion, keeping `openPdfSummary()`, `pageLabels()`, and `getPageImagePlan()` aligned.
- Added a focused fixture where comments split the catalog `/PageLabels` reference, `/Kids` entry, `/Nums` dictionary value reference, and indirect `/S` plus `/St` operands.
- Added a WordPress smoke that emits page-break metadata for `Cover-`, `Body 4`, and `Appendix-Z` while proving fallback physical labels are excluded.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL keeps comment-delimited PageLabels indirect references before WordPress page metadata
Expected: ['Cover-', 'Body 4', 'Appendix-Z']
Actual: ['1', '2', '3']
1 test files, 137 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 143 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-labels-comment-reference-currentbase.php
emits page_labels=[Cover-,Body 4,Appendix-Z], preview_page_labels=[Cover-,Body 4,Appendix-Z], fallback_labels_excluded=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Non-overlap

This does not repeat accepted PageLabels direct/indirect `/Nums`, indirect `/Kids`, inherited/local `/Limits`, indirect `/S` `/P` `/St` operands, transitive operands, signed integers, escaped catalog names, PDFDocEncoding prefixes, alphabetic formatting, generation-exact dictionaries, missing-generation rejection, object-stream PageLabels, top-level token-boundary decoys, malformed `/Limits`, or trailer-root selection. The bounded behavior is only comment-delimited indirect-reference tokens inside the PageLabels number-tree path.

## Dependency closure

No new support component is needed. The slice reuses the native PDF object scanner, generation-indexed object body table, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path. GPU/model OCR, Surya/Texify/Torch, pypdfium rendering, and external PDF tools remain intentionally out of scope.
