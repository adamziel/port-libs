# Page Resource Entry Generation Boundary Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T013144Z`

Base accepted HEAD: `b93f02b9243da78de4ef68e86851ccfec91bbc22`

## Source Truth

- PDF resource dictionaries are resolved through indirect object references that include object generation numbers.
- Upstream markerPDF delegates page-local text extraction to PDFium/pdftext before model stages. This native no-GPU boundary maps the parser requirement underneath that handoff: inherited `/Resources` entries must not reuse stale generation-zero objects when the resource entry references another generation.
- This stays within the current markerPDF no-GPU scope: searchable-PDF text extraction, page resource inheritance, Form XObject expansion, marked-content `/ActualText`, and page-boundary metadata only.

## Behavior

- `PdfTextExtractor` now resolves `/Font`, `/XObject`, and `/Properties` resource subdictionary entries through exact-generation resource references.
- Generation-mismatched inherited font resources no longer provide stale ToUnicode maps.
- Generation-mismatched inherited Form XObjects no longer expand stale form streams.
- Generation-mismatched inherited marked-content property resources no longer supply stale `/ActualText` replacement strings.
- `PdfPagePropertyExtractor` now filters stale resource subdictionary entry names from WordPress page-boundary review metadata while preserving the selected inherited resource dictionary and valid sibling entries.

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS filters generation-mismatched inherited resource entries before stale font form or ActualText reuse
1 test files, 15 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
9 PASS cases
4 test files, 106 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-resource-entry-generation-boundary-currentbase.php
Emits markerpdf:pdf-resource-entry-generation-boundary-currentbase with inherited_resource_dictionary_selected=true, valid_xobject_resource_preserved=true, stale_generation_resources_excluded=true, paragraphs ["A","B","Valid generation form text"], executes_python_or_models=false, executes_external_pdf_tools=false.
```

## Non-Overlap

This does not repeat accepted top-level `/Resources null` inheritance, malformed page `/Resources` fail-closed handling, generation-mismatched page `/Resources` references, legacy Form XObject resource inheritance, page-local font resource precedence, named destinations, xref repair, encryption, image/filter, annotation, or runtime model-preflight slices. The new boundary is specifically exact-generation filtering for individual entries inside an already-selected inherited page resource dictionary.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, generation-aware direct-object table, resource dictionary parser, stream decoder, font/CMap text extraction, Form XObject expansion, marked-content replacement, and page-boundary metadata extractors. GPU/OCR/model execution remains intentionally out of scope under the current markerPDF lane override.

## Next

Continue non-overlapping native searchable-PDF parser work around page resources, fonts, content streams, xref repair, metadata, annotations/forms, image/filter metadata, and supplied-boundary table/equation handoffs.
