# markerpdf AcroForm Escaped Page-Tree Boundary

Session: `port-dev-markerpdf-acroform-fields-20260606T033906Z`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260606T033906Z`
Base accepted HEAD: `71a2ed72a9b0c34179d3caee1b9b9a3d99213629`

## Source Truth

Upstream markerPDF delegates PDF parsing to searchable-PDF extraction support; this lane ports the native no-GPU PHP parser/converter behavior. PDF names allow `#xx` escapes, so page-tree names such as `/T#79pe /P#61ge` and `/K#69ds` must be decoded before AcroForm page-widget repair decides whether an omitted parent field is page-owned and safe to surface.

## Behavior

`PdfAcroFormExtractor` now uses the existing decoded PDF-name reader when identifying `/Type /Catalog` and `/Type /Page` objects for the AcroForm page map. This preserves listed field page metadata and repairs page-owned Widget annotations whose parent field is omitted from `/AcroForm /Fields`, while detached decoys outside the page annotation list remain excluded from form review and visible text.

## Focused Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsEscapedPageTreeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL maps escaped page tree names before AcroForm page widget boundary repair
Expected: array (
  0 => 'listed.escapedpage',
  1 => 'pageonly.escapedpage',
)
Actual: array (
  0 => 'listed.escapedpage',
)
1 test files, 1 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsEscapedPageTreeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS maps escaped page tree names before AcroForm page widget boundary repair
1 test files, 33 assertions, 0 failures
```

Focused family subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsEscapedPageTreeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageWidgetParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageTreeIndirectKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
4 test files, 616 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-escaped-page-tree-currentbase.php
```

The smoke marker reports `field_count=2`, `field_names=["listed.escapedpage","pageonly.escapedpage"]`, `listed_widget_page_owned=true`, `page_only_widget_repaired=true`, `detached_decoy_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This slice does not repeat existing AcroForm field-tree tests for direct dictionaries, indirect arrays, duplicate keys, object streams, stream objects, comments, escaped `/Parent` ownership, direct widget parent/no-Kids repair, or value/action/appearance metadata. It only changes page-tree type recognition feeding page-annotation ownership.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF-name decoder, page-tree walker, AcroForm field/widget repair logic, and WordPress smoke path. No OCR, Surya, Texify, Torch, PDFium, model workers, external PDF tools, or live provider services were run.

Root harness: not run - isolated micro-slice.
