# markerPDF Form Null Resource Inheritance Current Base

Lane: `markerpdf`
Slice: `markerpdf-page-resource-inheritance-current-base-20260605T031145Z`
Base accepted HEAD: `5fa3b785574733506c7d7bc664e972380aeaa321`

## Source Truth

- Upstream `sddai/markerPDF` at the pinned manifest commit extracts searchable PDF text through PDFium/pdftext page text before Marker block conversion.
- PDF page resources are the lookup context for content streams. Legacy Form XObjects that omit `/Resources` inherit the invoking resource context; a direct or indirect `null` `/Resources` value has the same absent-value boundary, while an explicit empty dictionary remains a local resource dictionary.

## Behavior

`PdfTextExtractor` now treats Form XObject `/Resources null` and exact-generation indirect null resource objects as omitted resource dictionaries. Direct-null and indirect-null Form XObjects can therefore invoke nested XObjects and fonts from the effective page resources. A Form XObject with `/Resources << >>` still stays explicit and does not inherit the page XObject map.

The WordPress smoke renders two paragraphs from direct-null and indirect-null Form resources and records that the explicit empty resource dictionary did not add a third nested-form paragraph. The path stays native PHP and does not execute Python, PDFium, OCR/model workers, or external PDF tools.

## Evidence

Focused resource test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 82 assertions, 0 failures
```

Adjacent resource/property family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
6 test files, 410 assertions, 0 failures
```

Form/image resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 327 assertions, 0 failures
```

Broader searchable-PDF text extractor:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 628 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-form-null-resource-inheritance-currentbase.php
```

The smoke emits `direct_null_form_resources_inherit_page=true`, `indirect_null_form_resources_inherit_page=true`, `explicit_empty_form_resources_block_page_xobject=true`, `visible_text_excludes_resource_names=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-tree `/Resources` inheritance, top-level page `/Resources null`, indirect-null page resources, leaf resource override, malformed/generation-mismatched page resources, top-level `/Parent` parsing, escaped page-tree `/Type`, legacy omitted Form resources, nested Form local resources, or image XObject generation review. The bounded behavior is only Form XObject direct/indirect null `/Resources` inheritance from the invoking page resource context.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, exact-generation object lookup, page-tree resource resolver, Form XObject expansion, CMap/font resource mapping, XObject resource lookup, and WordPress smoke path. Live OCR/model execution and exact upstream GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
