# markerPDF Page Resource Trailer Root Generation Current Base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T071708Z`
Base accepted HEAD: `70ef77d166e9d74b86c970021f8d31ae3fa55c57`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text and page metadata through PDF parser layers before model/OCR stages.
- PDF indirect references are generation-qualified. A trailer `/Root 1 1 R` must not silently bind to a stale `1 0 obj` catalog for page-tree traversal or inherited `/Resources` review metadata.

## Change

- `PdfPagePropertyExtractor::catalogObjectBody()` now resolves trailer `/Root` with exact object generation and verifies the resolved object is a Catalog.
- When a trailer `/Root` operand is present but malformed or generation-mismatched, page-boundary/page-review metadata fails closed instead of scanning stale unreferenced Catalog objects.
- Added a focused page-resource inheritance fixture where only stale `1 0 obj` has inherited `/Resources`, while the trailer names missing generation `1 1 R`.
- Added a WordPress smoke for the same review boundary.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
FAIL blocks page-resource review when trailer Root generation does not resolve to the current catalog
Expected: array (
)
Actual: array (
  0 =>
  array (
    'source' => 'page_boundary_review',
    'resources' =>
    array (
      'resource_owner_object' => 2,
      'resource_object' => 10,
      'inherited' => true,
      'categories' => array ('Font', 'XObject'),
      'font_names' => array ('F1'),
      'xobject_names' => array ('StaleRootForm'),
    ),
  ),
)
1 test files, 127 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 128 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-trailer-root-generation-currentbase.php
```

The smoke emits `generation_mismatched_trailer_root_blocks_resource_review=true`, `stale_catalog_resource_metadata_excluded=true`, `stale_font_resource_excluded=true`, `stale_xobject_resource_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, trailer dictionary reader, generation-aware indirect-reference parser, page-tree walker, page-resource metadata extractor, and WordPress smoke renderer. Live OCR/layout/table/equation models, PDFium/pdftext parity, and GPU/model benchmark parity remain intentionally out of scope for the no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, `/Resources null` inheritance, malformed `/Resources` fail-closed handling, generation-mismatched page `/Parent`, generation-mismatched page-tree `/Kids`, generation-mismatched resource entries, escaped `/Type` names, Form XObject resource scoping, xref `/Prev` repair, outline trailer-root selection, PageLabels generation work, or metadata trailer-root recovery. The bounded behavior is only generation-exact trailer `/Root` selection before page-resource review metadata can inherit stale catalog resources.
