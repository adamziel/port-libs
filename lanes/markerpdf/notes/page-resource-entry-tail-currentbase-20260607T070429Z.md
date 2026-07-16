# Page Resource Entry Tail Current Base - 2026-06-07

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260607T070429Z`

Accepted base: `292d5976e030b6a4dcfa5a457736d0b25d5a6de5`

## Behavior

Inherited page `/Resources` subdictionary entries that use indirect references
must be single values. This slice rejects `/Font`, `/XObject`, and
`/Properties` entries such as `/Ftailed 5 0 R 99 0 R` before they can drive
font CMaps, Form XObject expansion, or marked-content `/ActualText`
replacement.

The valid sibling entries in the same inherited resource dictionary still
resolve, so WordPress imports keep usable page fonts, forms, and accessible
text while excluding tailed decoy payloads from visible paragraphs and review
metadata.

## Source Truth

The upstream markerPDF boundary recorded in `UPSTREAM_TEST_MANIFEST.json` gets
searchable PDF text from pdftext/PDFium before model/OCR paths. At the PDF
resource grammar level, a resource subdictionary entry is a name followed by
one object value; a second non-name token before the next resource name is a
malformed entry, not an additional wrapper to chase. The native PHP parser now
keeps that boundary for inherited page resources without running Python,
PDFium, OCR, Surya, Texify, Torch, or external PDF tools.

## Evidence

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 240 assertions, 1 failures

FAIL rejects tailed inherited resource entry references before font or ActualText lookup
Actual lines included:
Tailed resource font leak
Tailed resource ActualText leak
```

Focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 255 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-entry-tail-currentbase.php
```

The smoke emits five WordPress paragraph blocks, rejects the tailed font,
ActualText, and Form XObject entries, reports only `Fvalid`, `ValidForm`, and
`ValidActual` in page-resource review metadata, and records
`executes_python_or_models=false` plus `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page resource inheritance, generation mismatch,
null resource inheritance, escaped/comment-delimited references, malformed
resource object/category tails, direct entry type filtering, stream-entry
guards, duplicate resource dictionaries, object-stream resource inheritance,
Form XObject resource inheritance, optional-content resources, or image/filter
metadata. The bounded behavior is only inherited resource subdictionary
entries whose indirect reference is followed by a non-name tail token.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object
scanner, page-resource resolver, resource-entry parser, CMap/font maps,
marked-content property parser, Form XObject expander, and WordPress smoke
path. Model/OCR parity remains intentionally out of scope under the no-GPU
markerPDF directive.
