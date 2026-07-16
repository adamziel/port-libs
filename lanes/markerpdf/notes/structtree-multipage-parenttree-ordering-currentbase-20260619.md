# StructTreeRoot multi-page ParentTree ordering current base

Slice: `plib-tuzwg.4`

## Behavior

Tagged searchable PDFs can omit `/Pg` on StructElem rows and rely on page
`/StructParents` ParentTree arrays for page and MCID anchoring. Those arrays are
keyed by MCID, not logical reading order, so native extraction now uses the
ParentTree to identify the page while preserving the catalog `/StructTreeRoot`
`/K` order.

The focused fixture covers two tagged pages separated by an untagged page gap,
with repeated visible text on both tagged pages. Ordering is driven by
StructElem object and MCID anchors, not by text matching.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfStructTreeRootMultiPageOrderingCurrentBaseTest.php
```

Result: `1 test files, 12 assertions, 0 failures`.

Adjacent StructTree/text extraction family:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfStructTreeRootMultiPageOrderingCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPageStructParentsAfThreadsCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAssociatedFilesMarkedContentAltCurrentBaseTest.php lanes/markerpdf/tests/PdfLayoutPageAnnotationStructTreeTableBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructTreeLayoutPreviewBundleCurrentBaseTest.php
```

Result: `6 test files, 841 assertions, 0 failures`.

Full markerPDF lane:

```sh
php tools/run-tests.php lanes/markerpdf/tests
```

Result: `1651 test files, 82887 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-structtree-multipage-ordering-currentbase.php
```

Result: emits `structtree_order_applied=true`,
`untagged_page_gap_preserved=true`, `duplicate_text_mcid_anchored=true`,
`rolemap_resolved=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Boundaries

No Python, OCR/model execution, PDFium, browsers, JavaScript, office suites, or
external PDF validators are invoked. The change is limited to native searchable
PDF tagged-structure ordering before WordPress block text rendering.
