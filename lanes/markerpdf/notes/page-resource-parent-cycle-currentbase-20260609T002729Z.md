# markerPDF page-resource parent-cycle current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260609T002729Z`
Session: `port-dev-markerpdf-resource-inherit-20260609T002729Z`
Base accepted HEAD: `28428232606f6fb6b3df81661dee1f40b90244b3`

## Source Truth

Upstream markerPDF receives page-bounded searchable text from pdftext/PDF parser layers before OCR or model stages. At this native no-GPU boundary, page-tree `/Resources` are inheritable through the selected catalog `/Pages` and `/Kids` path, but malformed cyclic `/Parent` chains must not allow off-path `/Pages` resources to become font, Form XObject, or marked-content lookup roots.

## Behavior

- `PdfTextExtractor` now detects a cyclic page `/Parent` walk and trims the effective resource lineage to the common selected catalog `/Kids` prefix.
- The text path marks that cyclic lineage as blocked, preventing fallback to cycle-only ancestors or root resources outside the trusted prefix.
- `PdfPagePropertyExtractor` uses the same trimmed lineage before page-boundary resource metadata, so review rows do not advertise cycle-only resource categories.
- Valid branch resources on the trusted catalog prefix remain usable for searchable WordPress paragraphs and Form XObject expansion.

## Evidence

Red-first focused run after adding the test and before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceParentCycleCurrentBaseTest.php
FAIL blocks cyclic page Parent resources outside the selected catalog Kids prefix
Expected: array (
  0 => 'A',
)
Actual: array (
  0 => 'Parent cycle font leak',
  1 => 'Parent cycle form leak',
)
1 test files, 18 assertions, 1 failures
```

Focused run after source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceParentCycleCurrentBaseTest.php
1 test files, 30 assertions, 0 failures
```

Adjacent page-resource lineage family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCatalogParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentNullCatalogPathCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceDuplicateParentCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceDuplicateKidsParentCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceLookupLineageCurrentBaseTest.php
9 test files, 427 assertions, 0 failures
```

Full page-resource family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfPageResource.*Test.php' | sort)
59 test files, 1304 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-parent-cycle-currentbase.php
```

The smoke exits 0 and emits `selected_prefix_resource_object=20`, `selected_prefix_resource_owner=10`, `resource_lookup_objects=[3,10]`, `cycle_resource_decoy_excluded=true`, `root_fallback_resource_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page-tree `/Kids` cycle guards, duplicate `/Kids` parent-lineage selection, duplicate `/Parent` key selection, detached parent rejection, parent wrapper references, null parent catalog-path repair, generation-mismatched parent rejection, malformed parent operands, null or malformed `/Resources`, direct resource entry tails, resource category wrappers, Form XObject null-resource inheritance, image XObject resource review, xref repair, metadata, annotations, forms, security preflight, table/equation handoff, or OCR/model behavior. The bounded behavior is only cyclic `/Parent` page-tree ancestors before inherited `/Resources` lookup.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, selected catalog page-tree walker, generation-exact parent/resource resolvers, CMap/font maps, Form XObject expansion, page-boundary review metadata, and WordPress smoke harness. Live OCR, PDFium/pypdfium rendering, Surya/Texify/Torch model execution, Streamlit/FastAPI model workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
