# markerPDF page resource object-tail current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260606T034309Z`
Base accepted HEAD: `24c52a21c864b6f386083d32c7a119569cc95770`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to parser/PDFium/pdftext layers before OCR and model stages. In the native no-GPU PHP boundary, page `/Resources` remains an inheritable page-tree attribute, but an indirect resource object must resolve to a valid PDF dictionary object before fonts, marked-content properties, ProcSet, or Form XObject names can drive WordPress import.

## Behavior

- `PdfTextExtractor` now resolves page and form resource objects with a single-dictionary guard.
- `PdfPagePropertyExtractor` uses the same guard for page-boundary resource review metadata.
- A `/Resources 10 0 R` object whose body is `<< ... >> 99 0 R` is treated as malformed instead of using the dictionary prefix.
- The page still emits safe fallback glyph text, while the stale resource font text, Form XObject payload, and resource names remain excluded from visible WordPress paragraphs.

## Evidence

Focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 239 assertions, 0 failures
```

Page-resource/property family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
19 test files, 808 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-object-tail-currentbase.php
```

The smoke emits `trailing_resource_object_rejected=true`, `resource_font_text_excluded=true`, `resource_form_text_excluded=true`, `resource_name_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-object-tail-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

Exploratory broader command including `PdfTextExtractorTest.php` reproduced the known unrelated ToUnicode `usecmap` failures already recorded by earlier page-resource notes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
20 test files, 1434 assertions, 2 failures
```

## Non-Overlap

This does not repeat accepted page-tree inherited resources, `/Resources null` inheritance, empty resource dictionaries, generation-exact resource references, comment-split references, duplicate top-level `/Resources`, malformed arrays, stream-valued `/Resources`, category stream rejection, ProcSet review metadata, Form XObject null-resource inheritance, image XObject inherited-owner review, page labels, annotations, forms, xref repair, CMaps, OCR, or model execution. The bounded behavior is only rejecting indirect page resource dictionary objects that contain non-comment trailing tokens after the dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, page-tree lineage resolver, resource dictionary resolver, page-boundary metadata extractor, text extractor, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
