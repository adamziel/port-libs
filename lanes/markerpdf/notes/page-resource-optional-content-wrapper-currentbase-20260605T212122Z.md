# markerPDF page resource optional-content wrapper current base

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T212122Z`

## Source truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser/PDFium/pdftext page structures before OCR/model stages. In this native no-GPU boundary, inherited page `/Resources /Properties` entries feed `/OC ... BDC` marked-content visibility for page content and invoked Form XObjects before WordPress paragraphs are emitted.

## Change

- `PdfTextExtractor::optionalContentReferenceVisible()` now resolves exact indirect wrapper chains before evaluating OCG/OCMD dictionaries.
- Inherited `/Resources /Properties` entries such as `/HiddenWrapped 31 0 R` where `31 0 obj` contains `21 0 R` now inherit the final OCG visibility state instead of defaulting to visible.
- The focused fixture covers both page content and a legacy Form XObject that omits `/Resources` and therefore inherits the page resource context.

## Red-first evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceOptionalContentWrapperCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL filters inherited optional-content Properties that wrap OCG references before WordPress text extraction
Expected: Base resource wrapper text, Visible wrapped layer text, Visible wrapped form text
Actual: Base resource wrapper text, Hidden wrapped layer text, Hidden wrapped form text, Visible wrapped form text, Visible wrapped layer text, Hidden wrapped form text, Visible wrapped form text
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceOptionalContentWrapperCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS filters inherited optional-content Properties that wrap OCG references before WordPress text extraction
1 test files, 11 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*.php
Focused test run: 16 selected test files (root lock skipped)
16 test files, 467 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-resource-optional-content-wrapper-currentbase.php
```

The WordPress smoke emits `wrapped_visible_layer_imported=true`, `wrapped_visible_form_imported=true`, `wrapped_hidden_layer_excluded=true`, `wrapped_hidden_form_excluded=true`, `resource_property_names_excluded_from_paragraphs=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

An additional adjacent run of `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` still fails two existing ToUnicode `usecmap` cases (`inherits ToUnicode usecmap mappings...` and `guards cyclic ToUnicode usecmap...`). The failure reproduces when that file is run alone and is outside this optional-content wrapper slice.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, exact-generation resource wrapper resolver, page-tree inherited resource lookup, optional-content visibility model, Form XObject expansion, and WordPress smoke renderer. OCR, Surya/Texify/Torch model execution, pypdfium/pdftext runner parity, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-overlap

This does not repeat accepted page-tree resource inheritance, null/malformed page `/Resources`, generation-exact resource references, resource dictionary/category wrappers, resource entry wrappers for fonts/XObjects/ActualText, ProcSet review metadata, direct optional-content layer visibility, optional-content usage/intent state, legacy Form omitted `/Resources` inheritance, image XObject resource review, page `/Contents` non-inheritance, xref repair, metadata, forms, annotations, or OCR/model handoffs. The bounded behavior is only inherited page resource `/Properties` entries that wrap OCG/OCMD references before optional-content marked-block filtering.
