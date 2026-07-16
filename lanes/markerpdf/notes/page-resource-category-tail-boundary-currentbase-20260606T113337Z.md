# Page Resource Category Tail Boundary Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260606T113337Z`
Base: `454eb2e80ab750c1392b21e50662320bbde7c428`

## Source Truth

- Upstream markerPDF conversion inherits page-tree resources for searchable PDFs, but resource dictionaries are parser lookup roots and malformed category operands must not promote stale Form XObjects or marked-content properties into visible WordPress text.
- Existing accepted markerPDF coverage already fails closed on malformed whole `/Resources` dictionary tails and on stream/malformed resource entries. This slice covers the narrower non-overlapping case where an otherwise valid inherited resource dictionary contains direct `/XObject` or `/Properties` category dictionaries followed by non-name trailing tokens.

## Behavior Added

- `PdfTextExtractor` now rejects direct resource-category dictionary values with trailing non-name tokens before Form XObject lookup, marked-content property lookup, and font/category helpers.
- `PdfPagePropertyExtractor` now omits metadata names for malformed direct resource category dictionaries with trailing non-name tokens.
- Valid sibling categories in the same inherited resource dictionary remain usable, so a valid `/Font` category still decodes searchable page text.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCategoryTailBoundaryCurrentBaseTest.php
=> FAIL; actual text leaked "Malformed category-tail ActualText leak" and "Malformed category-tail form leak"; 1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCategoryTailBoundaryCurrentBaseTest.php
=> 1 test files, 17 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*.php
=> 23 test files, 645 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*Test.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php
=> 47 test files, 433 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-resource-category-tail-boundary-currentbase.php
=> emits category_tail_form_excluded=true, category_tail_actual_text_excluded=true, font_category_preserved=true, xobject_category_tail_rejected=true, properties_category_tail_rejected=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF dictionary scanner and existing resource inheritance/review pipeline. GPU/model OCR, external PDF tools, and live-service runners remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat existing whole `/Resources` trailing-token rejection, resource stream-object rejection, generation-boundary resource lookup, direct/null inheritance, image XObject inheritance review, or Type3 marked-content work. It is scoped to malformed direct resource category subdictionaries inside otherwise valid inherited page resources.

## Next

Continue with non-overlapping searchable-PDF native parser fidelity: font/CMap edge cases, page geometry, stream filters, annotations/forms, image/filter metadata, xref repair, or supplied-boundary table/equation handoffs.
