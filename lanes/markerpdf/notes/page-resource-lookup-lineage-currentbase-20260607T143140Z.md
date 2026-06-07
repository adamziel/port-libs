# markerPDF Page Resource Lookup Lineage Current Base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260607T143140Z`

Accepted base: `85b59777f2df68a3c33983f7558f1f3864d76821`

## Behavior

PDF page `/Resources` is an inheritable page-tree attribute. In native parser
review, WordPress needs to know not only which object supplied the effective
resource dictionary, but also which page-tree objects were inspected before
that resource value was accepted or rejected.

`PdfPagePropertyExtractor` now adds `resource_lookup_objects` to page boundary
resource metadata. The list records the page-tree lookup path inspected until a
resolved resource dictionary is found or a malformed explicit page resource
value blocks parent fallback. Existing `resource_owner_object`,
`resource_object`, `resource_generation`, `inherited`, and category/name fields
remain unchanged.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceLookupLineageCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records page-tree resource lookup lineage for inherited branch resources without merging root decoys
Values are not identical
Expected: array (
  0 => 3,
  1 => 10,
)
Actual: NULL
FAIL records malformed page resource lookup lineage before parent fallback is blocked
Values are not identical
Expected: array (
  0 => 4,
)
Actual: NULL

1 test files, 10 assertions, 2 failures
```

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceLookupLineageCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records page-tree resource lookup lineage for inherited branch resources without merging root decoys
PASS records malformed page resource lookup lineage before parent fallback is blocked

1 test files, 19 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 34 selected test files (root lock skipped)
34 test files, 1078 assertions, 0 failures
```

Syntax and WordPress smoke:

```text
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfPagePropertyExtractor.php

php -l lanes/markerpdf/tests/PdfPageResourceLookupLineageCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageResourceLookupLineageCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-lookup-lineage-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-resource-lookup-lineage-currentbase.php

php lanes/markerpdf/examples/wordpress-pdf-page-resource-lookup-lineage-currentbase.php
```

The smoke emits `inherited_page_resource_lookup_objects=[3,10]`,
`inherited_page_resource_owner_object=10`,
`malformed_page_resource_lookup_objects=[4]`,
`malformed_page_resource_status=unresolved_or_malformed`,
`root_resource_decoy_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`, plus three Gutenberg paragraph blocks.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page-resource extraction, category merging, null
inheritance, direct/indirect resource tail rejection, stream-valued resource
guards, duplicate resource selection, Form XObject resource inheritance,
ProcSet metadata, image XObject resource-owner review, page-tree wrapper
resolution, or xref repair. The bounded change is only additive page boundary
review metadata for the effective resource lookup path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, page-tree lineage resolver, page-resource resolver, page boundary
metadata extractor, text extractor, and WordPress smoke renderer. Live OCR,
Surya/Texify/Torch model execution, PDFium/raster rendering, Streamlit/FastAPI
model workers, external PDF tools, and exact upstream model benchmark parity
remain intentionally out of scope under the current no-GPU markerPDF direction.
