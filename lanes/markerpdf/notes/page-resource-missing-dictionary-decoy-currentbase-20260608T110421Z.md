# Page Resource Missing Dictionary Decoy Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260608T110421Z`

Session: `port-dev-markerpdf-resource-inherit-20260608T110421Z`

Base accepted HEAD: `2685bfd2918d5f146742ce08f1f4ded2aa11745d`

## Source Truth

markerPDF's searchable-PDF import path delegates native text extraction to parser/pdftext-style page content parsing before OCR/model fallback. Under PDF page-resource semantics, page content and Form XObject lookup roots come from explicit `/Resources` dictionaries on the page tree. A page object that omits `/Resources` does not make arbitrary top-level `/Font` or `/XObject` entries valid resource dictionaries.

This slice keeps raw page content text extractable when `/Resources` is omitted, but prevents invalid top-level page resource decoys from being used to expand Form XObjects or leak resource names into WordPress paragraphs.

## Red-First Evidence

Before the source change, a one-off probe using a page with no `/Resources` and top-level `/Font << /F1 5 0 R >> /XObject << /DecoyForm 6 0 R >>` returned:

```php
array (
  0 => 'Page raw text',
  1 => 'Top-level page XObject decoy leak',
)
array (
)
```

The empty page resource metadata showed the resource dictionary was omitted, but text extraction still expanded the top-level XObject decoy.

## Patch

`PdfTextExtractor::expandedPageContentStreamWithFontMaps()` now expands page-content Form/XObject invocations only when `pageResourceDictionaryBody()` resolves an actual page-tree resource dictionary. It no longer falls back to treating the whole page object as a resource owner when `/Resources` is absent.

Focused coverage:

- `lanes/markerpdf/tests/PdfPageResourceMissingDictionaryDecoyCurrentBaseTest.php` asserts `extractTextLines()`, `extractTextRuns()`, styled text pages, `extractPlainText()`, and `naiveGetText()` keep only raw page text while excluding the top-level Form XObject decoy and resource name.
- The same test asserts `PdfPagePropertyExtractor::extractPageBoundaryMetadata()` returns no page-resource review rows when `/Resources` is omitted.
- `lanes/markerpdf/examples/wordpress-pdf-page-resource-missing-dictionary-decoy-currentbase.php` emits a WordPress paragraph for the raw searchable page text and a review comment showing `resource_metadata_absent=true`, `top_level_xobject_decoy_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Verification

Focused direct test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceMissingDictionaryDecoyCurrentBaseTest.php
```

Result: `1 test files, 9 assertions, 0 failures`.

Adjacent page-resource/Form XObject family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectForm*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageFormXObjectStructTreeClipCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
```

Result: `51 test files, 1450 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-resource-missing-dictionary-decoy-currentbase.php
```

Result: exits 0 with raw paragraph text preserved and decoy flags excluded.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct resource dictionary tails, null and indirect-null page resources, comment-delimited resource references, category stream/tail boundaries, generation filtering, resource lineage review, Form XObject malformed-resource blocking, image XObject review, annotation appearance inheritance, xref repair, OCR/model, or external PDF tool behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP object scanner, page-tree lineage/resource resolver, content tokenizer, Form XObject expander, page boundary metadata extractor, and WordPress smoke path. GPU/model OCR, Surya/Texify/Torch, raster execution, and live external services remain intentionally out of scope.
