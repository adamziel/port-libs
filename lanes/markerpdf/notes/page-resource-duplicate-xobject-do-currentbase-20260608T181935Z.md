# Page Resource Duplicate XObject Do Current Base, 2026-06-08

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260608T181935Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts page text through `pdftext.extraction.dictionary_output()` and pypdfium text pages before Marker block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Adobe PDF Reference 1.3 documents page `/Resources` as an inheritable page-tree attribute, names resources through the current resource dictionary for each content stream, and allows omitted Form XObject `/Resources` to fall back to the page resource dictionary: https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.3.pdf

## Change

- `PdfTextExtractor::xObjectResourceObjectNumbers()` now applies the same duplicate resource-name guard already used by XObject review metadata.
- Ambiguous duplicate inherited `/XObject` entries are excluded before `Do` Form XObject expansion, so neither the stale nor current duplicate form payload leaks into WordPress paragraphs.
- Valid sibling XObject resources still expand normally, and page-boundary metadata continues to report only unambiguous inherited XObject names.

## Red-First Evidence

Before the source change, the focused fixture selected the second duplicate `/DupForm` during Form expansion even though metadata excluded the duplicate name:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateXObjectDoBoundaryCurrentBaseTest.php
FAIL rejects duplicate inherited XObject Do names before Form expansion leaks stale page text
Expected: Duplicate XObject page text, Valid inherited XObject form text
Actual: Duplicate XObject page text, Current duplicate XObject form leak, Valid inherited XObject form text
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateXObjectDoBoundaryCurrentBaseTest.php
1 test files, 16 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*.php
52 test files, 1166 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-xobject-do-currentbase.php
exits 0 with duplicate_xobject_name_excluded=true, valid_xobject_name_retained=true, visible_paragraph_count=2, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, effective page resource resolver, resource subdictionary duplicate detector, Form XObject stream expansion, page-boundary metadata extractor, and WordPress smoke path. GPU/model OCR, pypdfium/PIL raster rendering, live Surya/Texify execution, and exact upstream model benchmark parity remain intentionally out of scope for the current no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted page resource null/omitted inheritance, parent lineage repair, comment-delimited references, object wrappers, malformed resource fail-closed behavior, duplicate font/property metadata filtering, page-local resource override behavior, or top-level category scoping. The new boundary is specifically the separate Form XObject `Do` expansion lookup for duplicate inherited XObject resource names.
