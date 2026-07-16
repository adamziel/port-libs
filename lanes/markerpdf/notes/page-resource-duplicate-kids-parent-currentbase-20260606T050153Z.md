# markerPDF Page Resource Duplicate Kids Parent Current Base

Lane: `markerpdf`
Slice: `markerpdf-page-resource-inheritance-current-base-20260606T050153Z`
Base accepted HEAD: `d0a134019583244d26aaca02c539b68c5c2f018e`

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to parser layers before OCR/model execution. At this native no-GPU boundary, inherited page `/Resources` follow the effective page-tree parent chain. When a malformed PDF lists the same page object in duplicate catalog `/Kids` branches, the explicit page `/Parent` chain should select the reachable `/Pages` resource owner instead of letting an earlier duplicate branch block font, Form XObject, and page-resource review metadata.

## Behavior

- `PdfTextExtractor` now asks catalog page-tree traversal to prefer a lineage matching the explicit `/Parent` prefix when duplicate `/Kids` entries reach the same page object.
- If no matching catalog lineage exists, the existing fail-closed parent/Kids membership behavior is preserved by falling back to the longest common catalog prefix.
- `PdfPagePropertyExtractor` mirrors the same duplicate-path preference so WordPress review metadata reports the same resource owner used for text extraction.
- The WordPress smoke proves object `20` resources win over the earlier duplicate object `10` branch; decoy font/Form XObject resources stay out of visible paragraphs.

## Evidence

Red-first focused run after adding the test and before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateKidsParentCurrentBaseTest.php
FAIL prefers explicit page Parent lineage when duplicate catalog Kids reach the same page object
Expected: array (
  0 => 'Current duplicate parent font text',
  1 => 'Current duplicate parent form text',
)
Actual: array (
  0 => 'A',
)
1 test files, 1 assertions, 1 failures
```

Focused run after source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateKidsParentCurrentBaseTest.php
1 test files, 13 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
20 test files, 821 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-kids-parent-currentbase.php
```

The smoke emits `explicit_parent_lineage_selected=true`, `current_resource_object_selected=true`, `current_font_and_form_selected=true`, `first_duplicate_parent_resource_excluded=true`, `decoy_form_uninvoked=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, omitted `/Parent` catalog path recovery, parent `/Kids` mismatch fail-closed behavior, detached parent exclusion, generation-mismatched `/Parent` or `/Kids`, top-level `/Resources null`, indirect null resources, malformed `/Resources` fail-closed behavior, escaped `/Kids` or `/Type`, Form XObject omitted/null resources, resource entry wrappers, stream-category resource rejection, image XObject inherited provenance, metadata, attachments, annotations, forms, xref repair, or OCR/model handoffs. The bounded behavior is only duplicate catalog `/Kids` entries where the page's explicit `/Parent` chain chooses a later reachable `/Pages` resource owner.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, exact-generation object inventory, catalog page-tree walker, resource dictionary resolver, Type0 CMap/font map extraction, Form XObject expansion path, page-boundary resource metadata, and WordPress smoke renderer. Full upstream pdftext/PDFium parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
