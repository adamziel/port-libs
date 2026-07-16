# Page Resource Generation Boundary Current Base

## Source Truth

- Upstream `sddai/markerPDF` delegates searchable PDF page text extraction to the pdftext/PDFium boundary before Marker block conversion.
- PDF page `/Resources` is an inheritable page-tree attribute only when omitted or null. If a page declares an indirect `/Resources` reference, that reference must resolve to the exact object generation named by the reference; a stale generation-zero object with the same object number is not the current page resource dictionary.
- For WordPress import, a generation-mismatched page resource declaration must fail closed before stale font maps, stale Form XObjects, or parent page-tree resources are promoted into visible Gutenberg paragraphs.

## Behavior

- `PdfTextExtractor` now resolves indirect page/Form resource dictionaries and indirect resource-category dictionaries through exact-generation resource lookup.
- `PdfPagePropertyExtractor` records direct object generations and reports generation-mismatched page `/Resources` references as `unresolved_or_malformed`, including `resource_generation`.
- The focused fixture declares page `/Resources 12 1 R` while only stale `12 0 obj` exists. Native extraction now emits only the raw fallback glyph `A`, excludes stale generation-zero resource text/forms, and does not inherit parent resources after the malformed page-level declaration.
- `examples/wordpress-pdf-page-resource-generation-boundary-currentbase.php` emits a WordPress paragraph for `A` plus native-only flags proving no Python, models, or external PDF tools executed.

## Evidence

Red-first before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
FAIL fails closed on generation-mismatched page Resources references before stale resource reuse or parent inheritance
Expected: array (0 => 'A')
Actual: array (0 => 'Stale generation font leak', 1 => 'Stale generation form leak')
1 test files, 42 assertions, 1 failures
```

Focused verification after the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 56 assertions, 0 failures
```

Broader focused verification after the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
5 test files, 947 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 232 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-generation-boundary-currentbase.php
generation_mismatch_fails_closed=true
stale_generation_resource_excluded=true
parent_resource_not_inherited_after_malformed_page_resource=true
visible_text_fallback_only=true
```

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-generation-boundary-currentbase.php
git diff --check -- lanes/markerpdf
all passed
```

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, xref-selected object owner metadata, direct object generation cache, page-tree resource resolver, content tokenizer, stream decoder, and WordPress smoke path. Full upstream OCR/layout/model parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, top-level `/Resources null`, indirect null resources, explicit empty resources, malformed array/missing resource references, nested private resource exclusion, legacy Form XObject omitted-`/Resources` fallback, leaf resource override, or page `/Contents` non-inheritance. The new boundary is exact-generation validation for indirect page resource dictionaries before native text/form lookup.
