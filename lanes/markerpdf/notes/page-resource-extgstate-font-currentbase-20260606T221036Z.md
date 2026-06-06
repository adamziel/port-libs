# markerPDF Page Resource ExtGState Font Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260606T221036Z`
Session: `port-dev-markerpdf-resource-inherit-20260606T221036Z`
Base accepted HEAD: `f6bfee1fc6ed174d758bd3c8b818fb1c8f0f930e`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed `pdftext`/PDFium page extraction before OCR, layout, table, and equation model stages. In the native no-GPU PHP scope, page `/Resources` inheritance must therefore carry text-state resources used by page content and Form XObjects before WordPress paragraphs are emitted.

PDF ExtGState dictionaries may set text state with `/Font [/Fname size]` and are selected by the content-stream `gs` operator. If those ExtGState resources are inherited from a `/Pages` ancestor or scoped to a Form XObject, the native parser must apply the selected font before text showing operators while preserving q/Q graphics-state restoration.

## Behavior

- `PdfTextExtractor` now rewrites valid inherited direct or indirect `/ExtGState` `/Font` arrays into equivalent `Tf` text-state tokens before the existing text-line, text-run, and styled-span scanners run.
- Page inherited ExtGState fonts survive across page resource inheritance and q/Q restoration.
- Form XObject local ExtGState `/Font` arrays, including direct ExtGState dictionaries, are rewritten with the same form-local font aliases used by explicit `Tf`, preventing stale page fonts with the same resource name from leaking into form text.
- Malformed `gs` operands, unresolved ExtGState entries, stream-valued ExtGState objects, and malformed `/Font` arrays are ignored instead of creating a new fallback path.

## Red-First Evidence

Before the production edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceExtGStateFontCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL applies inherited ExtGState Font arrays before page text extraction and restores q state
Expected: ['Inherited ExtGState font first', 'Private ExtGState scoped text', 'Inherited ExtGState font restored']
Actual: ['A', 'B', 'C']
FAIL aliases form-local ExtGState Font arrays before inherited page fonts can leak
Expected: ['Form-local ExtGState font text']
Actual: ['A']
1 test files, 2 assertions, 2 failures
```

After the production edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceExtGStateFontCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS applies inherited ExtGState Font arrays before page text extraction and restores q state
PASS aliases form-local ExtGState Font arrays before inherited page fonts can leak
1 test files, 17 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 28 selected test files (root lock skipped)
28 test files, 957 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-extgstate-font-currentbase.php
```

The smoke emits `page_extgstate_font_applied=true`, `form_local_extgstate_font_applied=true`, `stale_page_form_font_excluded=true`, `resource_review_reports_extgstate=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by two Gutenberg paragraphs.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, null or malformed `/Resources` handling, generation-exact `/Kids` or `/Parent`, duplicate `/Kids` parent lineage, ProcSet metadata, stream-valued resource category rejection, direct/indirect resource entry wrappers, Form XObject omitted/null `/Resources`, image XObject ExtGState transparency review, Type3-private ExtGState soft-mask fallback exclusion, xref repair, metadata, annotations, forms, or OCR/model handoffs. The bounded behavior is only inherited and form-local `/Resources /ExtGState` `/Font` text-state arrays before searchable WordPress text extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, page-resource inheritance resolver, ExtGState resource dictionary lookup, content-stream tokenizer, Form XObject font aliasing, text-line/text-run/styled-span scanners, and WordPress smoke harness. Live OCR, PDFium rendering parity, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the markerPDF no-GPU directive.
