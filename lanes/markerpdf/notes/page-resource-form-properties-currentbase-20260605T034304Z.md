# Page Resource Form Properties Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T034304Z`

Base accepted HEAD: `c33fe978011c86b89b0b5b76d4a85d768cddd9c2`

## Source Truth

- PDF marked-content property names are resource names. When a Form XObject declares its own `/Resources /Properties`, same-named properties in the invoking page resources must not replace the form-local `/ActualText` or `/Alt` values.
- Upstream markerPDF delegates searchable page text extraction to pdftext/PDFium before model stages. This native no-GPU slice maps the parser/resource boundary underneath that handoff: page and Form XObject resource scopes stay separate before WordPress paragraph rendering.
- This stays inside the current markerPDF lane scope: native PDF object parsing, page resources, Form XObject expansion, marked-content replacement, and WordPress import smoke coverage only.

## Behavior

- `PdfTextExtractor` now carries the resolved marked-content `/Properties` map through Form XObject expansion.
- Invoked Form XObjects alias their local marked-content property names before expansion, matching the existing font-resource aliasing boundary.
- A page and a Form XObject can both use `/SharedActual` while preserving page `/ActualText` on the page stream and form-local `/ActualText` or `/Alt` inside the form stream.
- Raw glyph fallback text from marked-content blocks stays excluded when `/ActualText` or `/Alt` is available from the correct resource scope.

## Focused Evidence

Red-first probe before the source change: a Form XObject using `/SharedActual BDC` with a form-local `/Resources /Properties` dictionary imported the form raw glyph text instead of the form-local replacement text.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 92 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPageFormXObjectStructTreeClipCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 1177 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-form-properties-currentbase.php
Emits markerpdf-page-resource-form-properties-currentbase-smoke with page_property_preserved=true, form_local_actual_text_imported=true, form_local_alt_text_imported=true, shared_property_name_scoped=true, raw_glyph_noise_excluded=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-Overlap

This does not repeat accepted top-level page resource inheritance, page `/Resources null`, malformed/generation-mismatched page resources, exact-generation resource entries, direct/indirect null Form XObject resources, nested Form font-resource aliasing, annotation appearance resource boundaries, optional-content visibility, image XObject resource review, xref repair, encryption preflight, or runtime model-preflight slices. The bounded behavior is only Form XObject marked-content `/Properties` scoping during native text extraction.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, page-tree resource resolver, resource dictionary parser, content tokenizer, Form XObject expander, stream decoder, font/CMap text extraction, marked-content replacement, and WordPress smoke path. GPU/OCR/model execution and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.

## Next

Continue non-overlapping native searchable-PDF parser work around page resources, fonts, content-stream operators, xref repair, metadata, annotations/forms, image/filter metadata, page geometry, and supplied-boundary table/equation handoffs.
