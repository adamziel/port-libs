# markerPDF page resource direct dictionary tail current-base

Slice: `markerpdf-page-resource-inheritance-current-base-20260606T061537Z`
Session: `port-dev-markerpdf-resource-inherit-20260606T061537Z`
Base: `98d37dedec48e231d559abd333dd1d6b05575268`

## Source truth

Upstream markerPDF delegates searchable-PDF text extraction to parser layers before OCR/model execution. In the native no-GPU scope, page `/Resources` is an inheritable page-tree attribute only when omitted or explicitly null. A present direct `/Resources <<...>>` page value must be a valid dictionary value, not a dictionary prefix followed by non-name operands that could make stale font or XObject resources look page-local.

## Behavior

- `PdfTextExtractor` now rejects direct page `/Resources` dictionary values when the top-level token after the dictionary is neither the next `/Name` key nor the dictionary end.
- `PdfPagePropertyExtractor` reports the same boundary as `unresolved_or_malformed` resource metadata instead of listing font or XObject categories from the malformed prefix.
- The WordPress smoke preserves raw searchable page text while excluding text reachable only through the malformed resource dictionary's font CMap or Form XObject invocation.

## Red-first evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDirectDictionaryTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on direct page Resources dictionaries with non-name trailing tokens
Expected: ['A']
Actual: ['Direct dictionary tail font leak', 'Direct dictionary tail form leak']
1 test files, 1 assertions, 1 failures
```

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDirectDictionaryTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on direct page Resources dictionaries with non-name trailing tokens
1 test files, 15 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 21 selected test files (root lock skipped)
21 test files, 836 assertions, 0 failures
```

Final focused verification:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfPagePropertyExtractor.php

php -l lanes/markerpdf/tests/PdfPageResourceDirectDictionaryTailBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageResourceDirectDictionaryTailBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-direct-dictionary-tail-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-resource-direct-dictionary-tail-currentbase.php

php lanes/markerpdf/examples/wordpress-pdf-page-resource-direct-dictionary-tail-currentbase.php
emits direct_resource_dictionary_tail_rejected=true, resource_categories_rejected=true, raw_searchable_text_preserved=true, resource_font_text_excluded=true, resource_form_text_excluded=true, resource_name_text_excluded=true, executes_python_or_models=false, executes_external_pdf_tools=false, and one Gutenberg paragraph containing A.
```

## Non-overlap

This does not repeat accepted page-tree resource inheritance, top-level `/Resources null`, indirect null resources, indirect `/Resources` object-tail rejection, duplicate `/Resources` keys, escaped `/Kids`, catalog path recovery, parent/Kids generation checks, resource category wrappers, stream-valued category rejection, ProcSet review metadata, image XObject inheritance review, Form XObject null-resource inheritance, page `/Contents` non-inheritance, xref repair, metadata, forms, annotations, or OCR/model handoffs. The bounded behavior is only direct page `/Resources <<...>>` dictionary values followed by non-name trailing operands before the next page dictionary key.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, top-level dictionary/value readers, page-resource resolver, page-boundary metadata extractor, text extractor, and WordPress smoke harness. Live OCR, PDFium rendering, Surya/Texify/Torch model execution, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
