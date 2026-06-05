# markerPDF page resource Kids token boundary current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T154006Z`
Session: `port-dev-markerpdf-resource-inherit-20260605T154006Z`
Base accepted HEAD: `0017586f0ec4000005e9e8925bd3a65b36b8c8d2`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text through parser/PDFium/pdftext page structures before OCR/model stages. At this native no-GPU boundary, page-tree `/Kids` arrays select child page-tree nodes by top-level indirect references only. References embedded inside nested dictionaries, nested arrays, strings, or comments are payload data and must not become page leaves or inherited `/Resources` lineage.

## Behavior

`PdfTextExtractor::pageTreeKidReferencesFromArray()` now walks top-level `/Kids` array operands with the token-aware PDF array reader and accepts only operands that are exactly one indirect reference. Nested dictionary and nested array payload references are ignored before:

- catalog page enumeration;
- page `/Parent` membership checks;
- inherited `/Resources` font map lookup;
- Form XObject expansion from inherited resources;
- WordPress visible paragraph output.

`PdfPagePropertyExtractor` already used top-level array items for this boundary, so the source change is limited to `PdfTextExtractor`.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceKidsTokenBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores nested page-tree Kids references before inherited resource lookup
FAIL fails closed when page Parent lists the child only through nested Kids payload references
1 test files, 2 assertions, 2 failures
```

The first failure imported a nested `/Kids` dictionary payload page as visible WordPress text. The second failure allowed a wrong `/Parent` to inherit fonts and a Form XObject because the page appeared only inside nested `/Kids` payload data.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceKidsTokenBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores nested page-tree Kids references before inherited resource lookup
PASS fails closed when page Parent lists the child only through nested Kids payload references
1 test files, 25 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 669 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-kids-token-boundary-currentbase.php
```

The smoke emits `page_count=1`, `review_page_count=1`, `resource_owner_object=2`, `resource_object=10`, `resource_inherited=true`, `nested_payload_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Standalone broad text-extractor check on this accepted base:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 617 assertions, 4 failures
```

Those failures are in pre-existing ToUnicode CMap row-count/usecmap/comment cases and are outside this page-tree `/Kids` token-boundary slice.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, escaped `/Kids` key lookup, indirect `/Kids` arrays, exact parent `/Kids` membership, generation-mismatched `/Kids`, catalog-path recovery for pages without `/Parent`, null or malformed `/Resources`, stream-valued resource category rejection, resource entry wrappers, ProcSet metadata, image XObject inheritance review, or Form XObject resource inheritance. The bounded behavior is only top-level tokenization of entries inside page-tree `/Kids` arrays before page-resource inheritance.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware array reader, generation-aware page-tree reference resolver, inherited resource dictionary resolver, Type0 CMap/font maps, Form XObject expansion, page-boundary review metadata, and WordPress smoke renderer. Full upstream pdftext/PDFium parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
