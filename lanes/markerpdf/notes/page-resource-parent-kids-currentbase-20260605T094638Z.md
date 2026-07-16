# markerPDF page resource parent Kids boundary current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T094638Z`
Session: `port-dev-markerpdf-resource-inherit-20260605T094638Z`
Base accepted HEAD: `54d4990abd113041d05e6000e22de0cf52a8be6c`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDFs through page-scoped parser layers before OCR/model execution. At the native no-GPU PHP boundary, page `/Resources` inheritance follows the page tree. A leaf `/Page` that points `/Parent` at a sibling `/Pages` object not listing the exact child in top-level `/Kids` is malformed and must not inherit that sibling node's fonts, Form XObjects, or resource review metadata into WordPress import output.

## Behavior

`PdfTextExtractor` now fails closed during page-resource lineage walking unless each `/Parent` `/Pages` node lists the current page or intermediate `/Pages` object as an exact object/generation child in `/Kids`.

`PdfPagePropertyExtractor` mirrors the same membership guard before reporting inherited page-resource metadata. A malformed page that is reachable from the catalog through one branch but whose `/Parent` points at a sibling branch now produces raw text with no inherited sibling font map, does not expand the sibling Form XObject, and does not emit sibling resource review rows.

Valid parent membership is preserved: when the parent `/Kids` array lists the exact page reference, inherited `/Font` and `/XObject` resources continue to decode text and review metadata.

## Verification

Red-first probe before implementation:

```text
PdfTextExtractor::extractTextLines() returned:
  Sibling parent font leak
  Sibling parent form leak

PdfPagePropertyExtractor::extractPageBoundaryMetadata() reported inherited resources from resource_owner_object=20.
```

Focused test after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php
```

Passed: 1 test file, 19 assertions, 0 failures.

Adjacent page-resource family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
```

Passed: 10 test files, 523 assertions, 0 failures.

Standalone broad text extractor check:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Current accepted-base risk: 1 test file, 617 assertions, 2 failures in unrelated stream-filter fallback expectations:

- `recovers stale stream Length with bounded endstream terminators before WordPress rendering`
- `fails closed on unsupported or corrupt stream filters before WordPress text parsing`

The page-resource inheritance family above is green and the patch does not touch stream decoding.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-resource-parent-kids-currentbase.php
```

Passed with `mismatched_parent_kids_blocks_resource_inheritance=true`, `sibling_parent_font_excluded=true`, `sibling_parent_form_excluded=true`, `page_boundary_resource_review_blocked=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, top-level `/Resources null`, indirect null `/Resources`, explicit empty dictionaries, malformed `/Resources` fail-closed behavior, generation-mismatched page `/Parent`, generation-mismatched catalog `/Pages` and page-tree `/Kids`, generation-mismatched resource entries, escaped `/Type` and `/Kids` names, Form XObject omitted/null `/Resources` inheritance, page `/Contents` non-inheritance, image XObject inherited resource provenance, stream/category resource entry rejection, or PageLabels number-tree work. The bounded behavior is only parent `/Kids` membership validation before inherited page `/Resources` lookup.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-aware page-tree walker, resource dictionary resolver, Form XObject expander, page-boundary review extractor, and WordPress smoke renderer. Full upstream pdftext/PDFium parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
