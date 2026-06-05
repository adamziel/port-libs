# markerPDF Page Resource Form Null Wrapper Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T124121Z`

Accepted base: `80358caf3312a42f3e5a37ace947626166339ea9`

## Source Truth

Upstream markerPDF routes searchable PDF text through parser-layer page and Form XObject resource lookup before OCR/model fallbacks. At the native no-GPU boundary, a Form XObject `/Resources` entry that resolves through one or more indirect objects to `null` is equivalent to omitted resources and inherits the invoking page resource context. A wrapper that resolves to an empty dictionary remains explicit and must not backfill page fonts or XObjects.

## Implementation

- `PdfTextExtractor::formXObjectResourcesInheritInvoker()` now uses the existing generation-exact recursive resource-reference resolver before deciding whether a Form XObject `/Resources` value is effectively `null`.
- Wrapped `null` Form resources now inherit invoking page `/Font` and `/XObject` resources, allowing nested legacy Form XObjects to render searchable text.
- Wrapped empty resource dictionaries remain explicit and do not inherit page resources.

## Evidence

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
FAIL inherits invoking page resources when Form XObject Resources resolves through an indirect null wrapper
Actual: array (
)
1 test files, 163 assertions, 1 failures
```

Focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 169 assertions, 0 failures
```

Adjacent page-resource/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
9 test files, 1145 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-form-null-wrapper-currentbase.php
wrapped_null_form_inherits_page_resources=true
wrapped_empty_form_resources_stay_explicit=true
raw_resource_names_exposed=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-form-null-wrapper-currentbase.php
php -r '$data=json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
git diff --check -- lanes/markerpdf
```

All passed. Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, generation-aware resource reference resolver, page-tree resource inheritance path, Form XObject expansion path, and WordPress smoke harness. GPU/model OCR, Surya/Texify/Torch execution, pypdfium rendering, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted page-tree `/Resources` inheritance, leaf override/no-merge behavior, page `/Resources` indirect wrapper resolution, direct or single-hop indirect `null` Form resources, explicit empty Form resources, malformed page-resource fail-closed handling, generation-mismatched resource references, parent/kid lineage validation, escaped page-tree names, Form-local marked-content scoping, image XObject inherited-owner review, stream/category resource rejection, xref repair, or font-width behavior. The bounded behavior is only Form XObject `/Resources` wrapper chains whose final exact-generation value is `null` before nested Form resource lookup.
