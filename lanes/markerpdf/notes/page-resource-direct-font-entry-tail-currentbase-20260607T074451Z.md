# markerPDF Page Resource Direct Font Entry Tail Current Base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260607T074451Z`

Accepted base: `3d2d3e6ef4226dffa58dcb186275876022069cff`

## Behavior

Upstream markerPDF gets searchable PDF text from parser-backed PDF text
extraction before OCR/model stages. At the native parser boundary, each page
resource subdictionary entry is one name followed by one PDF object value. A
direct font dictionary followed by another non-name token before the next
resource name is malformed and must not drive ToUnicode lookup.

`PdfTextExtractor::directFontResourceDictionaries()` now rejects tailed direct
`/Font` resource dictionaries before building font maps. Valid direct sibling
font dictionaries in the same inherited resource object are still usable for
WordPress text import and review metadata.

## Evidence

Focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDirectFontEntryTailCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed direct Font resource dictionaries before ToUnicode lookup while preserving valid siblings

1 test files, 17 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 32 selected test files (root lock skipped)
32 test files, 1045 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-direct-font-entry-tail-currentbase.php
```

The smoke emits two paragraph blocks, reports `font_names=["Fvalid"]`, and
sets `tailed_direct_font_dictionary_rejected=true`,
`valid_direct_font_dictionary_preserved=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inherited indirect resource entry tail rejection,
direct `/Resources` dictionary tails, indirect resource-object tails,
resource-category tails, stream-entry guards, generation-mismatched resource
entries, comment-delimited references, object-stream resource inheritance,
Form XObject resource inheritance, optional-content resources, or image/filter
metadata. The bounded behavior is only direct `/Font <<...>>` resource
dictionary entries with non-name tail operands.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object
scanner, page-resource resolver, direct font resource parser, CMap/ToUnicode
font-map builder, page-resource review metadata path, and WordPress smoke
renderer. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium raster
paths, Streamlit/FastAPI model workers, external PDF tools, and exact upstream
model benchmark parity remain intentionally out of scope under the current
no-GPU markerPDF direction.
